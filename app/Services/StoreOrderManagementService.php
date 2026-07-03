<?php

namespace App\Services;

use App\Events\Tracking\TimelineEventTriggered;
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
    public function cancelSubOrder(SubOrder $subOrder, int $userId, string $reason): SubOrder
    {
        if (in_array($subOrder->status, ['in_transit', 'delivered', 'cancelled'])) {
            throw new Exception('This sub-order cannot be cancelled at this stage.');
        }

        return DB::transaction(function () use ($subOrder, $userId, $reason) {
            // 1. Update Sub-Order Status strictly at the merchant item level
            $subOrder->update(['status' => 'cancelled']);

            $subOrder->loadMissing('order');

            DB::table('ledgers')->insert([
                'order_id' => $subOrder->order_id,
                'sub_order_id' => $subOrder->id,
                'user_id' => $subOrder->order->customer_id, 
                'amount_minor_unit' => -$subOrder->subtotal_minor_unit, 
                'currency_code' => 'NGN',
                'transaction_type' => 'refund',
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Sync Parent Order state (Evaluates total fallout or progression)
            $this->syncParentOrderStatus($subOrder->order_id, $userId, $subOrder);

            // 4. Notify Customer UI about this specific sub-order failure
            event(new TimelineEventTriggered($subOrder->order_id, [
                'status' => 'sub_order_cancelled',
                'message' => "Items from {$subOrder->store->name} are unavailable: {$reason}",
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
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        DispatchOrderCascade::dispatch($order)->afterCommit();

        event(new TimelineEventTriggered($order->id, [
            'status' => 'searching_for_driver',
            'message' => 'Your order has been accepted and is being prepared.',
        ]));
    }

    /**
     * Executes fully locked parent system cancellation adjustments.
     */
    private function executeFullOrderCancellation(Order $order, int $userId, string $oldParentStatus, SubOrder $subOrder): void
    {
        $order->update(['status' => 'cancelled']);

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
}
