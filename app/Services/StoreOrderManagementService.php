<?php

namespace App\Services;

use App\Models\SubOrder;
use App\Models\Order;
use App\Models\OrderStateTransition;
use App\Events\Tracking\TimelineEventTriggered;
use Illuminate\Support\Facades\DB;
use Exception;

class StoreOrderManagementService
{
    /**
     * Accepts a sub-order and synchronizes the parent order state.
     */
    public function acceptSubOrder(SubOrder $subOrder, int $userId): SubOrder
    {
        if ($subOrder->status !== 'pending_acceptance') {
            throw new Exception("Only pending sub-orders can be accepted.");
        }

        return DB::transaction(function () use ($subOrder, $userId) {
            $oldStatus = $subOrder->status;
            
            // 1. Update Sub-Order Status
            $subOrder->update(['status' => 'accepted']);

            // 2. Log Transition
            OrderStateTransition::create([
                'order_id' => $subOrder->order_id,
                'from_status' => $oldStatus,
                'to_status' => 'accepted',
                'triggered_by_user_id' => $userId,
                'metadata' => json_encode(['sub_order_id' => $subOrder->id])
            ]);

            // 3. Sync Parent Order
            $this->syncParentOrderStatus($subOrder->order_id);

            // 4. Notify Customer asynchronously via Queue
            event(new TimelineEventTriggered($subOrder->order_id, [
                'status' => 'accepted',
                'message' => 'The store is preparing your order.',
                'store_id' => $subOrder->store_id
            ]));

            return $subOrder;
        });
    }

    /**
     * Cancels a sub-order and processes necessary ledger adjustments.
     */
    public function cancelSubOrder(SubOrder $subOrder, int $userId, string $reason): SubOrder
    {
        if (in_array($subOrder->status, ['in_transit', 'delivered', 'cancelled'])) {
            throw new Exception("This sub-order cannot be cancelled at this stage.");
        }

        return DB::transaction(function () use ($subOrder, $userId, $reason) {
            $oldStatus = $subOrder->status;
            
            // 1. Update Sub-Order Status
            $subOrder->update(['status' => 'cancelled']);

            // 2. Log Transition
            OrderStateTransition::create([
                'order_id' => $subOrder->order_id,
                'from_status' => $oldStatus,
                'to_status' => 'cancelled',
                'triggered_by_user_id' => $userId,
                'metadata' => json_encode(['sub_order_id' => $subOrder->id, 'reason' => $reason])
            ]);

            // 3. Process Ledger / Refund Logic (Placeholder)
            // Ledger::create(['sub_order_id' => $subOrder->id, 'transaction_type' => 'refund'...]);

            // 4. Sync Parent Order
            $this->syncParentOrderStatus($subOrder->order_id);

            // 5. Notify Customer
            event(new TimelineEventTriggered($subOrder->order_id, [
                'status' => 'cancelled',
                'message' => "Order cancelled by store: {$reason}",
                'store_id' => $subOrder->store_id
            ]));

            return $subOrder;
        });
    }

    /**
     * Evaluates all sub-orders to determine the correct parent order status.
     */
    private function syncParentOrderStatus(int $orderId): void
    {
        $order = Order::with('subOrders')->lockForUpdate()->findOrFail($orderId);
        
        $statuses = $order->subOrders->pluck('status')->unique();

        if ($statuses->count() === 1 && $statuses->first() === 'accepted') {
            $order->update(['status' => 'accepted']);
        } elseif ($statuses->contains('cancelled') && !$statuses->contains('accepted')) {
            $order->update(['status' => 'cancelled']);
        }
        // Additional state machine logic for mixed states (e.g., partial cancellations)
    }
}