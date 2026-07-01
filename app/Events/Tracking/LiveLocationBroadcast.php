<?php

namespace App\Events\Tracking;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveLocationBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Use 'ShouldBroadcastNow' so Laravel pushes it to the socket
     * instantly without waiting on a queue worker.
     */
    public function __construct(
        public int $orderId,
        public float $latitude,
        public float $longitude
    ) {}

    /**
     * Broadcast strictly on the private channel dedicated to this specific order.
     */
    public function broadcastOn(): Channel
    {
        return new Channel("orders.{$this->orderId}.tracking");
    }

    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }
}
