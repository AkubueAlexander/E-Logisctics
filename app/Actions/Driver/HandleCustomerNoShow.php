<?php

namespace App\Actions\Driver;

use App\Models\Order;
use App\Models\User;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HandleCustomerNoShow
{
    /**
     * Terminate the delivery route under a customer no-show exception clause.
     */
    public function execute(Order $order, User $driver): Order
    {
        // 1. Guard Clause: Multi-tenant security check
        if ($order->driver_id !== $driver->id) {
            throw new RuntimeException('Unauthorized: You are not the assigned courier for this order.');
        }

        if ($order->status !== 'in_transit') {
            throw new RuntimeException('Exception workflows can only be triggered while an order is actively in transit.');
        }

        return DB::transaction(function () use ($order, $driver) {
            // 2. Pivot statuses to auditing exception states
            $order->update(['status' => 'cancelled_no_show']);
            $order->subOrders()->update(['status' => 'cancelled_no_show']);

            // 3. Immediately liberate the driver so they can get back on the map pool
            $driver->driverProfile->update([
                'availability_status' => 'available'
            ]);

            // 4. LEDGER PROTECTION RULES:
            // Since the driver and stores completed their duties, we flip their pending lines to 'cleared'.
            // The platform keeps its commission, and the customer charge remains captured.
            Ledger::query()
                ->where('order_id', $order->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cleared',
                    'settled_at' => now(),
                    'metadata' => json_encode([
                        'exception_type' => 'customer_no_show',
                        'resolved_by_driver_id' => $driver->id,
                    ])
                ]);

            return $order;
        });
    }
}
