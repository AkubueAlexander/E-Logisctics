<?php

namespace App\Services;

use App\Events\Tracking\TimelineEventTriggered;
use App\Jobs\CancelUnassignedOrder;
use App\Jobs\DispatchOrderCascade;
use App\Models\DeliveryMission;
use App\Models\Order;
use App\Models\OrderStateTransition;
use App\Models\SubOrder;
use Exception;
use Illuminate\Support\Facades\DB;

class StoreOrderManagementService
{
    /**
     * Accepts a sub-order at the merchant item level.
     */
    public function acceptSubOrder(SubOrder $subOrder, int $userId): SubOrder
    {
        if ($subOrder->status !== 'pending_acceptance') {
            throw new Exception('Only pending sub-orders can be accepted.');
        }

        return DB::transaction(function () use ($subOrder, $userId) {
            // 1. Update Sub-Order Status strictly at the merchant item level
            $subOrder->update(['status' => 'accepted']);

            // 2. Sync Parent Order state (Let it handle parent logging when all stores respond)
            $this->syncParentOrderStatus($subOrder->order_id, $userId, $subOrder);

            // 3. Notify Customer UI about this specific merchant's confirmation
            event(new TimelineEventTriggered($subOrder->order_id, [
                'status' => 'sub_order_accepted',
                'message' => "Your items from {$subOrder->store->name} have been confirmed.",
                'store_id' => $subOrder->store_id,
            ]));

            return $subOrder;
        });
    }

    /**
     * Cancels a single sub-order at the merchant item level.
     */
    public function cancelSubOrder($subOrderId, $userId, $reason)
    {
        $subOrder = SubOrder::with(['order', 'store'])->findOrFail($subOrderId);

        // If already cancelled, do nothing to prevent double refunds
        if ($subOrder->status === 'cancelled') {
            return $subOrder;
        }

        return DB::transaction(function () use ($subOrder, $userId, $reason) {
            // 1. Update Sub-Order Status
            $subOrder->update(['status' => 'cancelled']);

            $customerId = $subOrder->order->customer_id;
            $storePayout = $subOrder->subtotal_minor_unit - $subOrder->platform_commission_minor_unit;

            // 2. Ensure Customer Wallet Exists & Fund It
            $this->ensureUserWallet($customerId);
            DB::table('wallets')
                ->where('owner_type', 'App\Models\User')
                ->where('owner_id', $customerId)
                ->increment('balance_minor_unit', $subOrder->subtotal_minor_unit);

            // 3. Append Reversal Ledgers (Using negative entries for append-only integrity)
            DB::table('ledgers')->insert([
                [
                    'order_id'          => $subOrder->order_id,
                    'sub_order_id'      => $subOrder->id,
                    'user_id'           => $customerId,
                    'amount_minor_unit' => -$subOrder->subtotal_minor_unit,
                    'transaction_type'  => 'customer_refund',
                    'status'            => 'completed',
                    'created_at'        => now(), 'updated_at' => now(),
                ],
                [
                    'order_id'          => $subOrder->order_id,
                    'sub_order_id'      => $subOrder->id,
                    'store_id'          => $subOrder->store_id,
                    'amount_minor_unit' => -$storePayout,
                    'transaction_type'  => 'store_payout_reversal',
                    'status'            => 'completed',
                    'created_at'        => now(), 'updated_at' => now(),
                ],
                [
                    'order_id'          => $subOrder->order_id,
                    'sub_order_id'      => $subOrder->id,
                    'amount_minor_unit' => -$subOrder->platform_commission_minor_unit,
                    'transaction_type'  => 'platform_commission_reversal',
                    'status'            => 'completed',
                    'created_at'        => now(), 'updated_at' => now(),
                ]
            ]);

            // 4. Sync Parent Order State (Remaining sub-orders will proceed seamlessly)
            $this->syncParentOrderStatus($subOrder->order_id, $userId, $subOrder);

            // 5. Trigger Timeline Updates
            event(new TimelineEventTriggered($subOrder->order_id, [
                'status'   => 'sub_order_cancelled',
                'message'  => "Items from {$subOrder->store->name} are unavailable: {$reason}",
                'store_id' => $subOrder->store_id,
            ]));

            return $subOrder;
        });
    }

    /**
     * Evaluates all sub-orders to transition and record parent order audit trails accurately.
     */
    private function syncParentOrderStatus(int $orderId, int $userId, SubOrder $subOrder): void
    {
        // Pessimistic lock ensures concurrent store evaluations don't overwrite each other
        $order = Order::with('subOrders')->lockForUpdate()->findOrFail($orderId);
        $oldParentStatus = $order->status;

        $statuses = $order->subOrders->pluck('status');

        // Guard Clause: If any store is still deciding, parent order stays in 'pending_acceptance'
        if ($statuses->contains('pending_acceptance')) {
            return;
        }

        // ------------------------------------------------------------------------
        // Scenario A: Total Failure (All stores cancelled their allocations)
        // ------------------------------------------------------------------------
        $allCancelled = $statuses->every(fn ($status) => $status === 'cancelled');
        if ($allCancelled) {
            $this->executeFullOrderCancellation($order, $userId, $oldParentStatus, $subOrder);

            return;
        }

        // ------------------------------------------------------------------------
        // Scenario B: Viable Progression (At least one store accepted, zero pending)
        // ------------------------------------------------------------------------

        if ($oldParentStatus !== 'pending_acceptance') {
            return;
        }

        $order->update(['status' => 'searching_for_driver']);

        // LOG REAL TRANSITION: Record the actual step the parent order just took
        OrderStateTransition::create([
            'order_id' => $order->id,
            'from_status' => $oldParentStatus,
            'to_status' => 'searching_for_driver',
            'triggered_by_user_id' => $userId,
            'metadata' => json_encode(['context' => 'All merchants responded. Order proceeds to fulfillment.']),
        ]);

        DeliveryMission::create([
            'order_id'                => $order->id,
            'driver_id'               => null,
            'status'                  => 'searching_for_driver',
            'delivery_fee_minor_unit' => $order->delivery_fee_minor_unit ?? 0,
            'metadata'                => json_encode([
                'initial_search_radius_km' => 5,
                'sub_order_allocations'    => $order->subOrders->pluck('id')->toArray(),
                'initiated_at'             => now()->toIso8601String(),
            ])
        ]);



        DB::afterCommit(function () use ($order) {
            // 1. Safe to notify customer - DB state is guaranteed
            event(new TimelineEventTriggered($order->id, [
                'status' => 'searching_for_driver',
                'message' => 'Your order has been accepted and is being prepared.',
            ]));

            // Kick off your dispatch engine jobs from Approach B
            DispatchOrderCascade::dispatch($order);

            // Start the 30-minute cancellation safety net timer!
            CancelUnassignedOrder::dispatch($order->id)->delay(now()->addMinutes(30));
        });
    }

    /**
     * Executes fully locked parent system cancellation adjustments.
     */
    private function executeFullOrderCancellation(Order $order, int $userId, string $oldParentStatus, SubOrder $subOrder): void
    {
        $order->update(['status' => 'cancelled']);

        $customerId = $order->customer_id;
        $deliveryFee = $order->delivery_fee_minor_unit ?? 0;
        $serviceFee = $order->service_fee_minor_unit ?? 0;
        $globalFeesToRefund = $deliveryFee + $serviceFee;

        // 1. Refund global delivery and platform fees back to customer wallet
        if ($globalFeesToRefund > 0) {
            $this->ensureUserWallet($customerId);

            DB::table('wallets')
                ->where('owner_type', 'App\Models\User')
                ->where('owner_id', $customerId)
                ->increment('balance_minor_unit', $globalFeesToRefund);

            // 2. Write global reversal entries into append-only ledger
            DB::table('ledgers')->insert([
                [
                    'order_id'          => $order->id,
                    'sub_order_id'      => null,
                    'user_id'           => $customerId,
                    'amount_minor_unit' => -$deliveryFee,
                    'transaction_type'  => 'delivery_escrow_reversal',
                    'status'            => 'completed',
                    'created_at'        => now(), 'updated_at' => now(),
                ],
                [
                    'order_id'          => $order->id,
                    'sub_order_id'      => null,
                    'amount_minor_unit' => -$serviceFee,
                    'transaction_type'  => 'global_service_fee_reversal',
                    'status'            => 'completed',
                    'created_at'        => now(), 'updated_at' => now(),
                ]
            ]);
        }

        // LOG REAL TRANSITION: Only log parent cancellation when the order is completely dead
        OrderStateTransition::create([
            'order_id' => $order->id,
            'from_status' => $oldParentStatus,
            'to_status' => 'cancelled',
            'triggered_by_user_id' => $userId,
            'metadata' => json_encode(['reason' => 'All sub-orders were cancelled by merchants.']),
        ]);

        event(new TimelineEventTriggered($order->id, [
            'status' => 'cancelled',
            'message' => 'Your order was cancelled because items are completely unavailable.',
        ]));
    }

    /**
     * Ensures polymorphic wallet maps correctly to App\Models\User.
     * Accommodates both drivers and customers sharing the exact same user table layout.
     */
    protected function ensureUserWallet($userId, string $currency = 'NGN')
    {
        return DB::table('wallets')->firstOrCreate(
            [
                'owner_type' => 'App\Models\User',
                'owner_id'   => $userId,
            ],
            [
                'balance_minor_unit' => 0,
                'currency'           => $currency,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]
        );
    }
}
