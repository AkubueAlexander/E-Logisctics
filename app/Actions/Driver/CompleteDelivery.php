<?php

namespace App\Actions\Driver;

use App\Models\Order;
use App\Models\User;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompleteDelivery
{
    /**
     * Finalize the route execution path and distribute locked escrow financial records.
     */
    public function execute(Order $order, User $driver): Order
    {
        // Guard Clause: Multi-tenant scope defense mapping
        if ($order->driver_id !== $driver->id) {
            throw new RuntimeException('Unauthorized: You are not the assigned courier for this delivery route.');
        }

        if ($order->status !== 'in_transit') {
            throw new RuntimeException('Orders can only be marked as completed if they are actively in transit.');
        }

        return DB::transaction(function () use ($order, $driver) {
            // 1. Set terminal delivery completion statuses
            $order->update(['status' => 'delivered']);
            $order->subOrders()->update(['status' => 'delivered']);

            // 2. Open up the driver's telemetry state to accept new pings
            $driver->driverProfile->update([
                'availability_status' => 'available'
            ]);

            // 3. Release financial records from Escrow
            // This flushes delivery fees to driver, and subtotal payouts directly to store metrics
            Ledger::where('order_id', $order->id)
                ->where('status', 'pending')
                ->update([
                    'status'     => 'cleared',
                    'settled_at' => now()
                ]);

            return $order;
        });
    }
}
