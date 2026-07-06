<?php

namespace App\Listeners\Telemetry;

use App\Events\DriverLocationUpdated;
use Illuminate\Support\Facades\Redis;

class UpdateGeospatialIndex
{
    /**
     * INTELLIGENCE: Synchronous Redis Ingestion
     * No 'ShouldQueue' here. High-frequency coordinates bypass MySQL completely
     * to protect database disk IOPS under heavy traffic.
     */
    public function handle(DriverLocationUpdated $event): void
    {
        // 1. Index the coordinate by order context using real properties
        // (orderId, longitude, latitude, heading)
        Redis::geoadd(
            'orders:spatial_index',
            $event->longitude,
            $event->latitude,
            (string) $event->orderId
        );

        // 2. Cache structural telemetry metadata for ultra-fast API lookups
        Redis::hmset("orders:telemetry:{$event->orderId}", [
            'latitude'   => $event->latitude,
            'longitude'  => $event->longitude,
            'heading'    => $event->heading,
            'updated_at' => now()->timestamp
        ]);
    }
}