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
use Illuminate\Support\Facades\Cache;

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
        // 1. Prevent overlapping cascades using an atomic Redis lock per order
        $lockKey = "order_dispatch_lock:{$this->order->id}";

        Cache::lock($lockKey, 10)->get(function () use ($finder) {

            // 2. Fetch fresh state using the optimized latestOfMany relationship
            $freshOrder = $this->order->fresh(['latestDeliveryMission', 'subOrders.store']);

            if (!$freshOrder || $freshOrder->status !== 'searching_for_driver' || $freshOrder->driver_id !== null) {
                Log::info("DispatchCascade: Order #{$this->order->id} is no longer eligible for dispatching.");
                return;
            }

            $mission = $freshOrder->latestDeliveryMission;

            if (!$mission) {
                sleep(1);
                $freshOrder = $this->order->fresh(['latestDeliveryMission', 'subOrders.store']);
                $mission = $freshOrder?->latestDeliveryMission;
            }

            if (!$mission) {
                Log::error("DispatchCascade: Critical Error - DeliveryMission metadata missing for Order #{$freshOrder->id}");
                return;
            }

            // 3. Circuit Breaker: Ensure there isn't an active, non-expired ping already out there
            $hasActivePing = MissionPing::where('delivery_mission_id', $mission->id)
                ->where('status', 'sent')
                ->where('expires_at', '>', now())
                ->exists();

            if ($hasActivePing) {
                Log::warning("DispatchCascade: Order #{$freshOrder->id} already has an active pending offer. Skipping duplicate iteration.");
                return;
            }

            // 4. Execute PostGIS/Spatial Query for drivers within a 5.0 km radius
            $radiusKm = 5.0;
            $nearbyDrivers = $finder->execute($freshOrder, $radiusKm);

            if ($nearbyDrivers->isEmpty()) {
                Log::warning("DispatchCascade: Zero online drivers found within {$radiusKm}km for Order #{$freshOrder->id}. Retrying loop in 60s.");
                self::dispatch($freshOrder)->delay(now()->addSeconds(180));
                return;
            }

            // 5. Collect excluded driver IDs who have already been targeted for this mission
            $alreadyTargetedDriverIds = MissionPing::where('delivery_mission_id', $mission->id)
                ->pluck('driver_id')
                ->toArray();

            // 6. Extract the next closest driver who hasn't been pinged yet
            // Note: Ensure $nearbyDrivers collection already eager-loads the 'user' relationship to avoid N+1 issues below
            $nextDriverProfile = $nearbyDrivers->first(function ($profile) use ($alreadyTargetedDriverIds) {
                return !in_array($profile->id, $alreadyTargetedDriverIds);
            });

            // Loop reset check: If all nearby drivers have been exhausted, expand search windows or cool down
            if (!$nextDriverProfile) {
                Log::info("DispatchCascade: All available local drivers exhausted for Order #{$freshOrder->id}. Resetting sequence in 60s.");
                self::dispatch($freshOrder)->delay(now()->addSeconds(180));
                return;
            }

            // 7. Atomic transaction to write the offer record
            $pingDurationSeconds = 180;
            $ping = DB::transaction(function () use ($mission, $nextDriverProfile, $pingDurationSeconds) {
                return MissionPing::create([
                    'delivery_mission_id' => $mission->id,
                    'driver_id'           => $nextDriverProfile->id,
                    'status'              => 'sent',
                    'expires_at'          => now()->addSeconds($pingDurationSeconds),
                ]);
            });

            Log::info("DispatchCascade: Dispatched offer Ping #{$ping->id} to Driver #{$nextDriverProfile->user_id} for Order #{$freshOrder->id}");

            // 8. Broadcast to the targeted driver's device via WebSockets (Laravel Reverb)
            broadcast(new NewMissionOfferDispatched($freshOrder, $nextDriverProfile->user, $pingDurationSeconds));

            // 9. Chain the deterministic timeout monitor job (Passing ONLY the ID)
            CheckPingTimeoutJob::dispatch($ping->id)->delay(now()->addSeconds($pingDurationSeconds + 1));
        });
    }
}
