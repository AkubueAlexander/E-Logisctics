<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\MissionPing;
use App\Jobs\HandlePingTimeout;
use App\Actions\Order\FindNearbyDriversForOrder;
use App\Events\NewMissionOfferDispatched;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchOrderToDrivers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public Order $order) {}

    /**
     * Execute the dispatching orchestration loop.
     */
    public function handle(FindNearbyDriversForOrder $finder): void
    {
        // 1. Resolve a single fresh instance to check all database properties accurately
        $freshOrder = $this->order->fresh();

        // Guard Clause: Prevent dispatching if deleted, already claimed, or changed state
        if (!$freshOrder || $freshOrder->status !== 'searching_for_driver' || $freshOrder->driver_id !== null) {
            return;
        }

        // 2. Fire PostGIS spatial matching query execution
        $radiusKm = 5.0;
        $drivers = $finder->execute($freshOrder, $radiusKm);

        if ($drivers->isEmpty()) {
            Log::warning("DispatchEngine: No online couriers found within {$radiusKm}km for Order #{$freshOrder->id}. Retrying cascade backoff.");

            // Release back to queue to check again in 20 seconds
            $this->release(20);
            return;
        }

        /** * Tell the IDE explicitly what model is coming out of the collection
         * @var \App\Models\DriverProfile $targetProfile
         */
        $targetProfile = $drivers->first();
        $assignedDriverUser = $targetProfile->user;

        Log::info("DispatchEngine: Matched Order #{$freshOrder->id} to Driver User #{$assignedDriverUser->id}");

        // 3. Resolve the underlying delivery mission relationship safely
        $mission = $freshOrder->deliveryMission;
        if (!$mission) {
            Log::error("DispatchEngine: DeliveryMission relation record missing for Order #{$freshOrder->id}");
            return;
        }

        // 4. Persist the high-performance tracking ping record into the database
        $ping = MissionPing::create([
            'delivery_mission_id' => $mission->id,
            'driver_id'           => $assignedDriverUser->id,
            'status'              => 'sent',
            'expires_at'          => now()->addSeconds(30),
        ]);

        // 5. Push live to the driver's device via Reverb WebSockets
        broadcast(new NewMissionOfferDispatched($freshOrder, $assignedDriverUser, 30));

        // 6. Lock in the event-driven countdown handler to check back in exactly 30 seconds
        HandlePingTimeout::dispatch($ping->id)->delay(now()->addSeconds(30));
    }
}
