<?php

namespace App\Jobs;

use App\Models\DeliveryMission;
use App\Models\MissionPing;
use App\Models\DriverProfile;
use App\Events\DriverPinged; // You will create this Reverb event next
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PingNearestDriverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public DeliveryMission $mission) {}

    public function handle(): void
    {
        // 1. Abort if the mission was already accepted while this job was in the queue
        if ($this->mission->status !== 'searching') {
            return;
        }

        // 2. We use the first Store's coordinates as the pickup origin point
        $firstStore = $this->mission->order->subOrders->first()->store;
        $originLat = $firstStore->latitude;
        $originLng = $firstStore->longitude;

        // 3. THE POSTGIS SPATIAL QUERY
        // Find the absolute closest available driver within 5KM (5000 meters)
        // who has NOT already received a ping for this specific mission.
        $closestDriver = DriverProfile::select('user_id', 'location')
            ->where('availability_status', 'available')
            ->whereRaw("ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326), 5000)", [$originLng, $originLat])
            ->whereNotIn('user_id', function ($query) {
                $query->select('driver_id')
                    ->from('mission_pings')
                    ->where('delivery_mission_id', $this->mission->id);
            })
            // Order by exact spherical distance so we strictly ping the nearest guy first
            ->orderByRaw("ST_DistanceSphere(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)) ASC", [$originLng, $originLat])
            ->first();

        // 4. Handle edge case: No drivers available within 5KM
        if (!$closestDriver) {
            Log::warning("No available drivers found for Mission #{$this->mission->id}. Retrying in 1 minute.");
            // Delay and retry the loop later to see if someone comes online
            PingNearestDriverJob::dispatch($this->mission)->delay(now()->addMinutes(1));
            return;
        }

        // 5. Lock in the Ping Record
        $ping = MissionPing::create([
            'delivery_mission_id' => $this->mission->id,
            'driver_id' => $closestDriver->user_id,
            'status' => 'sent',
            'expires_at' => now()->addSeconds(30),
        ]);

        // 6. Broadcast to the Driver's App via Laravel Reverb
        broadcast(new DriverPinged($ping, $this->mission->load('order.subOrders.store')));

        // 7. Dispatch the 30-Second Watchdog
        CheckPingTimeoutJob::dispatch($ping)->delay(now()->addSeconds(30));
    }
}
