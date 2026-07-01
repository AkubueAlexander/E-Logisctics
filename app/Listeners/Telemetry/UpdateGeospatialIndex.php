<?php

namespace App\Listeners\Telemetry;

use App\Events\DriverLocationUpdated;
use Illuminate\Support\Facades\Redis;

class UpdateGeospatialIndex
{
    /**
     * NO 'ShouldQueue' here. GPS streams run live directly into Redis.
     */
    public function handle(DriverLocationUpdated $event): void
    {
        // Matches primitive primitives: orderId, latitude, longitude, heading
        Redis::geoadd(
            "order:tracking:coordinates",
            $event->longitude,
            $event->latitude,
            (string) $event->orderId
        );

        Redis::hmset("order:tracking:meta:{$event->orderId}", [
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'heading' => $event->heading,
            'updated_at' => now()->timestamp
        ]);
    }
}
