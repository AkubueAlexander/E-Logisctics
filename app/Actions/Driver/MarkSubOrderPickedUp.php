<?php

namespace App\Actions\Driver;

use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class MarkSubOrderPickedUp
{
    public function execute(User $driver, SubOrder $subOrder): SubOrder
    {
        return DB::transaction(function () use ($driver, $subOrder) {

            $order = $subOrder->order;

            // Guard 1: Ownership
            if ($order->driver_id !== $driver->id) {
                throw new AccessDeniedHttpException('Unauthorized: You are not assigned to this mission.');
            }

            // Guard 2: Strict State Enforcement
            if ($subOrder->status !== 'driver_arrived') {
                throw new UnprocessableEntityHttpException(
                    "Cannot pick up items. Current status is '{$subOrder->status}', expected 'driver_arrived'."
                );
            }

            // 1. Advance the SubOrder
            $subOrder->update([
                'status' => 'in_transit'
            ]);

            // 2. Synchronize the Parent Order
            // If ALL sub-orders attached to this master order are now in transit, advance the parent.
            $allSubOrdersInTransit = $order->subOrders()
                ->where('status', '!=', 'in_transit')
                ->doesntExist();

            if ($allSubOrdersInTransit && $order->status !== 'in_transit') {
                
                // Capture the real current status before mutating the model
                $previousStatus = $order->status;

                $order->update([
                    'status' => 'in_transit'
                ]);

                // Log the orchestration state transition with the correct DB columns
                $order->stateTransitions()->create([
                    'from_status'          => $previousStatus,
                    'to_status'            => 'in_transit',
                    'triggered_by_user_id' => $driver->id, // <-- Appending the driver's User ID here
                    'metadata'             => [
                        'action_trigger' => 'driver_pickup_complete'
                    ], // <-- Safeguarding string tags inside your jsonb column
                ]);
            }

            return $subOrder;
        });
    }
}