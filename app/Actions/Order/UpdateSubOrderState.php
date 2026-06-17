<?php

namespace App\Actions\Order;

use App\Jobs\CancelUnassignedOrder;
use App\Jobs\DispatchOrderToDrivers;
use App\Models\SubOrder;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class UpdateSubOrderState
{
    /**
     * Advance a sub-order's workflow state and synchronize the parent orchestration layout.
     */
    public function execute(SubOrder $subOrder, array $data): SubOrder
    {
        return DB::transaction(function () use ($subOrder, $data) {

            // 1. Apply mutations directly to the child basket node
            $subOrder->status = $data['status'];

            if (isset($data['estimated_prep_time_minutes'])) {
                $subOrder->estimated_prep_time_minutes = $data['estimated_prep_time_minutes'];
            }

            $subOrder->save();

            // 2. Resolve the state machine mapping for the Parent Order
            $this->synchronizeParentOrder($subOrder->order);

            return $subOrder;
        });
    }

    /**
     * Evaluates sibling statuses to update the parent order status accurately.
     */
    protected function synchronizeParentOrder(Order $order): void
    {
        // Fetch all fresh sub-order statuses tied to this checkout event
        $statuses = $order->subOrders()->pluck('status')->toArray();
        $uniqueStatuses = array_unique($statuses);

        $newParentStatus = null;

        // Rule A: If ALL sub-orders have been accepted, kick off the driver hunt immediately
        if (!in_array('pending_acceptance', $statuses) && in_array('accepted', $statuses)) {
            $newParentStatus = 'searching_for_driver';
        }
        // Rule B: If all sub-orders are completely cancelled, mark the entire parent order cancelled
        elseif (count($uniqueStatuses) === 1 && current($uniqueStatuses) === 'cancelled') {
            $newParentStatus = 'cancelled';
        }

        // Apply state transition only if changes are determined
        if ($newParentStatus && $order->status !== $newParentStatus) {

            // Log the transition history
            $order->stateTransitions()->create([
                'from_status' => $order->status,
                'to_status'   => $newParentStatus,
                'triggered_by' => 'all_merchants_accepted',
            ]);

            $order->status = $newParentStatus;
            $order->save();

            // Offload spatial matching calculation to background workers immediately
            if ($newParentStatus === 'searching_for_driver') {
                DispatchOrderToDrivers::dispatch($order);
                CancelUnassignedOrder::dispatch($order->id)->delay(now()->addMinutes(30));
            }
        }
    }
}
