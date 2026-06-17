<?php

namespace App\Actions\Driver;

use App\Models\MissionPing;
use App\Models\User;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AcceptOrder
{
    public function execute(MissionPing $ping, User $driver): MissionPing
    {
        return DB::transaction(function () use ($ping, $driver) {

            // 1. Pessimistic Lock on the Delivery Mission to prevent any concurrency race conditions
            $mission = $ping->deliveryMission()->lockForUpdate()->first();

            if (!$mission) {
                throw new RuntimeException('The target delivery mission does not exist.');
            }

            // 2. Guard Clause: Check if the mission was already claimed by someone else or timed out
            if ($mission->status !== 'searching') {
                throw new RuntimeException('This request has already been claimed or is no longer available.');
            }

            // 3. Guard Clause: Check if this specific driver's ping is still in 'sent' state
            if ($ping->status !== 'sent') {
                throw new RuntimeException('This offer has expired or was already responded to.');
            }

            // 4. Update the Specific Ping Status
            $ping->update([
                'status' => 'accepted'
            ]);

            // 5. Lock down the Delivery Mission Wrapper
            $mission->update([
                'status' => 'picking_up',
                'driver_id' => $driver->id,
            ]);

            // 6. Synchronize the Parent Order Assignment
            $order = $mission->order;
            $order->update([
                'driver_id' => $driver->id,
                'status' => 'driver_assigned' // Transitions parent route to the collection phase
            ]);

            // 7. Update Driver Profile to 'busy' using your exact enum value
            $driver->driverProfile()->update([
                'availability_status' => 'busy'
            ]);

            // 8. Financial Ledger Allocation: Book the driver's delivery fee into escrow
            Ledger::create([
                'order_id' => $order->id,
                'sub_order_id' => null,
                'transaction_type' => 'driver_payout',
                'store_id' => null,
                'user_id' => $driver->id,
                'amount_minor_unit' => $mission->delivery_fee_minor_unit,
                'currency_code' => 'NGN',
                'status' => 'pending', // Settle only when final drop-off is verified
            ]);

            return $ping;
        });
    }
}
