<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\MissionPing;
use App\Actions\Order\FindNearbyDriversForOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB; // Fixed typo here

class DispatchOrderCascade implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; 

    public function __construct(protected Order $order) {}

    public function handle(FindNearbyDriversForOrder $finder): void
    {
        // 1. Guard Clause: Refresh and check status
        if ($this->order->fresh()->status !== 'ready_for_pickup') {
            return;
        }

        // Fetch the delivery mission wrapper for relation tracking
        $mission = $this->order->deliveryMission;
        if (!$mission) {
            return; 
        }

        // 2. Pull current candidate pool within 5km radius
        $nearbyDrivers = $finder->execute($this->order, 5.0);

        if ($nearbyDrivers->isEmpty()) {
            self::dispatch($this->order)->delay(now()->addSeconds(30));
            return;
        }

        // 3. Schema Fix: Query targeted drivers using delivery_mission_id, not order_id
        $alreadyTargetedDriverIds = MissionPing::where('delivery_mission_id', $mission->id)
            ->pluck('driver_id')
            ->toArray();

        $nextDriverProfile = $nearbyDrivers->first(function ($profile) use ($alreadyTargetedDriverIds) {
            return !in_array($profile->user_id, $alreadyTargetedDriverIds);
        });

        if (!$nextDriverProfile) {
            self::dispatch($this->order)->delay(now()->addSeconds(60));
            return;
        }

        // 4. Scope & Status Fix: Return the ping from the transaction and use 'sent'
        $ping = DB::transaction(function () use ($mission, $nextDriverProfile) {
            return MissionPing::create([
                'delivery_mission_id' => $mission->id, // Schema fix
                'driver_id'           => $nextDriverProfile->user_id,
                'status'              => 'sent', // Alignment fix: CheckPingTimeoutJob looks for 'sent'
                'expires_at'          => now()->addSeconds(45),
            ]);
        });

        // TODO: broadcast(new NewMissionOfferDispatched($ping));

        // 5. Recommended Job Integration: Safely pass the $ping instance
        CheckPingTimeoutJob::dispatch($ping)->delay(now()->addSeconds(46));
    }
}
