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
use Illuminate\Support3\Facades\DB;

class DispatchOrderCascade implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Handled programmatically via timeouts

    public function __construct(protected Order $order) {}

    public function handle(FindNearbyDriversForOrder $finder): void
    {
        // Guard Clause: If the order was already claimed or cancelled, terminate cascade execution
        if ($this->order->fresh()->status !== 'ready_for_pickup') {
            return;
        }

        // 1. Pull current candidate pool within 5km radius
        $nearbyDrivers = $finder->execute($this->order, 5.0);

        if ($nearbyDrivers->isEmpty()) {
            // No drivers found? Re-queue job to look again in 30 seconds (backoff loop)
            self::dispatch($this->order)->delay(now()->addSeconds(30));
            return;
        }

        // 2. Identify a target candidate who hasn't already explicitly rejected this mission ping
        $alreadyTargetedDriverIds = MissionPing::where('order_id', $this->order->id)
            ->pluck('driver_id')
            ->toArray();

        $nextDriverProfile = $nearbyDrivers->first(function ($profile) use ($alreadyTargetedDriverIds) {
            return !in_array($profile->user_id, $alreadyTargetedDriverIds);
        });

        if (!$nextDriverProfile) {
            // We've exhausted all current options within 5km. Reset or wait for new check-ins.
            self::dispatch($this->order)->delay(now()->addSeconds(60));
            return;
        }

        // 3. Dispatch the high-impact tracking ping record
        DB::transaction(function () use ($nextDriverProfile) {
            $ping = MissionPing::create([
                'order_id' => $this->order->id,
                'driver_id' => $nextDriverProfile->user_id,
                'status' => 'pending',
                'expires_at' => now()->addSeconds(45), // 45-second window to accept/reject
            ]);

            // TODO: Broadcast this ping model over Reverb channels straight to the driver's phone app
            // broadcast(new NewMissionOfferDispatched($ping));
        });

        // 4. Queue up a check job to expire this offer if they ignore it
        CheckPingTimeout::dispatch($this->order)->delay(now()->addSeconds(46));
    }
}
