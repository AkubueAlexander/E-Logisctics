<?php

namespace App\Listeners\Telemetry;

use App\Events\DriverPinged;
use Illuminate\Support\Facades\Redis;

class TrackDriverHeartbeat
{
    /**
     * NO 'ShouldQueue' here. High-frequency pings run synchronously in memory.
     */
    public function handle(DriverPinged $event): void
    {
        // Matches constructor types: public MissionPing $ping, public DeliveryMission $mission
        $ping = $event->ping;

        Redis::setex(
            "dispatch:active_ping:{$ping->driver_id}",
            35,
            $event->mission->id
        );
    }
}
