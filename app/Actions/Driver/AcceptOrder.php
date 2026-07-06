<?php

namespace App\Actions\Driver;

use App\Models\Ledger;
use App\Models\MissionPing;
use App\Models\OrderStateTransition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AcceptOrder
{
    public function execute(MissionPing $ping, User $driver): MissionPing
    {
        return DB::transaction(function () use ($ping, $driver) {

            // 1. Pessimistic Lock on the Delivery Mission to prevent any concurrency race conditions
            $mission = $ping->deliveryMission()->lockForUpdate()->first();

            if (! $mission) {
                throw new RuntimeException('The target delivery mission does not exist.');
            }

            // 2. Guard Clause: Check if the mission was already claimed by someone else or timed out
            if ($mission->status !== 'searching_for_driver') {
                throw new RuntimeException('This request has already been claimed or is no longer available.');
            }

            // 3. Guard Clause: Check if this specific driver's ping is still in 'sent' state
            if ($ping->status !== 'sent') {
                throw new RuntimeException('This offer has expired or was already responded to.');
            }

            // 4. Update the Specific Ping Status
            $ping->update([
                'status' => 'accepted',
            ]);

            // 5. Lock down the Delivery Mission Wrapper
            $mission->update([
                'status' => 'picking_up',
                'driver_id' => $driver->id,
            ]);

            // 6. Synchronize the Parent Order Assignment
            $order = $mission->order;
            $oldStatus = $order->status;

            $order->update([
                'driver_id' => $driver->id,
                'status' => 'driver_assigned', // Transitions parent route to the collection phase
            ]);

            OrderStateTransition::create([
                'order_id' => $order->id,
                'from_status' => $oldStatus,
                'to_status' => 'driver_assigned',
                'triggered_by_user_id' => $driver->id, // The driver's user ID caused this transition
                'metadata' => json_encode([
                    'context' => 'Driver explicitly accepted the delivery mission offer.',
                    'mission_id' => $mission->id,
                    'ping_id' => $ping->id,
                ]),
            ]);

            // 7. Update Driver Profile to 'busy' using your exact enum value
            $driver->driverProfile()->update([
                'availability_status' => 'busy',
            ]);

            // 8. Financial Ledger Allocation: Book the driver's delivery fee into escrow
            $deliveryFee = $order->delivery_fee_minor_unit;

            // A. Neutralize the anonymous escrow.
            // It MUST be 'pending' so it cancels out the original pending payment entry.
            Ledger::create([
                'order_id' => $order->id,
                'sub_order_id' => null,
                'transaction_type' => 'delivery_escrow_reversal',
                'store_id' => null,
                'user_id' => null,
                'amount_minor_unit' => -$deliveryFee, // Negative to offset
                'currency_code' => 'NGN',
                'status' => 'pending', // Matches original status
            ]);

            // B. Track the new liability for the assigned driver
            Ledger::create([
                'order_id' => $order->id,
                'sub_order_id' => null,
                'transaction_type' => 'driver_payout',
                'store_id' => null,
                'user_id' => $driver->id,
                'amount_minor_unit' => $deliveryFee, // Positive liability
                'currency_code' => 'NGN',
                'status' => 'pending', // Stays pending until drop-off
            ]);

            return $ping;
        });
    }
}
