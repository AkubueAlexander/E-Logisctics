<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Notice we use ShouldBroadcastNow, NOT ShouldBroadcast.
// We want this to bypass the Redis queue and fire instantly.
class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $driverId,
        public int $orderId,
        public float $latitude,
        public float $longitude,
        public float $heading // Required so the front-end knows which way to rotate the car icon
    ) {}

    public function broadcastOn(): array
    {
        // This broadcasts to a private room dedicated solely to this specific order
        return [
            new PrivateChannel("App.Models.Order.{$this->orderId}.Tracking")
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'  => $this->orderId,
            'latitude'  => $this->latitude,
            'longitude' => $this->longitude,
            'heading'   => $this->heading,
            'timestamp' => now()->timestamp,
        ];
    }
}
