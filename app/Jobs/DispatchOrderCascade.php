<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\MissionPing;
use App\Actions\Order\FindNearbyDriversForOrder;
use App\Events\NewMissionOfferDispatched;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchOrderCascade implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Set tries to 1 because the cascade handles its own iterative state loop.
     */
    public int $tries = 1;

    public function __construct(protected Order $order) {}

    public function handle(FindNearbyDriversForOrder $finder): void
    {
        // 1. Fetch fresh state with relations (Lock removed as it's useless outside a transaction)
        $freshOrder = $this->order->fresh(['deliveryMission']);

        if (!$freshOrder || $freshOrder->status !== 'searching_for_driver' || $freshOrder->driver_id !== null) {
            Log::info("DispatchCascade: Order #{$this->order->id} is no longer eligible for dispatching.");
            return;
        }

        $mission = $freshOrder->deliveryMission;
        if (!$mission) {
            Log::error("DispatchCascade: Critical Error - DeliveryMission metadata missing for Order #{$freshOrder->id}");
            return;
        }

        // 2. Execute PostGIS/Spatial Query for drivers within a 5.0 km radius
        $radiusKm = 5.0;
        $nearbyDrivers = $finder->execute($freshOrder, $radiusKm);

        if ($nearbyDrivers->isEmpty()) {
            Log::warning("DispatchCascade: Zero online drivers found within {$radiusKm}km for Order #{$freshOrder->id}. Retrying loop in 30s.");
            self::dispatch($freshOrder)->delay(now()->addSeconds(30));
            return;
        }

        // 3. Collect excluded driver IDs who have already been targeted for this mission
        $alreadyTargetedDriverIds = MissionPing::where('delivery_mission_id', $mission->id)
            ->pluck('driver_id')
            ->toArray();

        // 4. Extract the next closest driver who hasn't been pinged yet
        $nextDriverProfile = $nearbyDrivers->first(function ($profile) use ($alreadyTargetedDriverIds) {
            return !in_array($profile->user_id, $alreadyTargetedDriverIds);
        });

        // Loop reset check: If all nearby drivers have been exhausted, expand search windows or cool down
        if (!$nextDriverProfile) {
            Log::info("DispatchCascade: All available local drivers exhausted for Order #{$freshOrder->id}. Resetting sequence in 60s.");
            self::dispatch($freshOrder)->delay(now()->addSeconds(60));
            return;
        }

        // 5. Atomic transaction to write the offer record
        $pingDurationSeconds = 60;
        $ping = DB::transaction(function () use ($mission, $nextDriverProfile, $pingDurationSeconds) {
            return MissionPing::create([
                'delivery_mission_id' => $mission->id,
                'driver_id'           => $nextDriverProfile->user_id,
                'status'              => 'sent',
                'expires_at'          => now()->addSeconds($pingDurationSeconds),
            ]);
        });

        Log::info("DispatchCascade: Dispatched offer Ping #{$ping->id} to Driver #{$nextDriverProfile->user_id} for Order #{$freshOrder->id}");

        // 6. Broadcast to the targeted driver's device via WebSockets (Laravel Reverb)
        broadcast(new NewMissionOfferDispatched($freshOrder, $nextDriverProfile->user, $pingDurationSeconds));

        // 7. Chain the deterministic timeout monitor job (Passing ONLY the ID)
        CheckPingTimeoutJob::dispatch($ping->id)->delay(now()->addSeconds($pingDurationSeconds + 1));
    }
}