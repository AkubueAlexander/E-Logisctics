<?php

namespace App\Listeners\Telemetry;

use App\Events\DriverLocationUpdated;
use Illuminate\Support\Facades\Redis;

class TrackDriverHeartbeat
{
    /**
     * NO 'ShouldQueue' here. High-frequency pings run synchronously in memory.
     */
    public function handle(DriverLocationUpdated $event): void
    {
        // Atomically set/refresh the driver's live presence key in Redis with a 35-second TTL
        Redis::setex(
            "dispatch:active_ping:{$event->driverId}",
            35,
            $event->orderId
        );
    }
}