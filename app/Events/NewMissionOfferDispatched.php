<?php

namespace App\Events;

use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMissionOfferDispatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
        public User $driver,
        public int $timeoutSeconds = 30
    ) {}

    /**
     * Route the broadcast to a private channel secured for this specific driver.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("drivers.{$this->driver->id}")
        ];
    }

    /**
     * Define the data matrix the driver's mobile client receives.
     */
    public function broadcastWith(): array
    {
        $firstStore = $this->order->subOrders->first()?->store;

        return [
            'order_id' => $this->order->id,
            'delivery_fee_formatted' => number_format($this->order->delivery_fee_minor_unit / 100, 2),
            'currency' => 'NGN',
            'timeout_seconds' => $this->timeoutSeconds,
            'pickup' => [
                'store_name' => $firstStore?->name ?? 'Multi-Vendor Pickup',
                'latitude' => $firstStore?->latitude,
                'longitude' => $firstStore?->longitude,
            ],
            'dropoff' => [
                'address' => $this->order->delivery_address,
                'latitude' => $this->order->latitude,
                'longitude' => $this->order->longitude,
            ]
        ];
    }

    /**
     * The WebSocket client-side event name to listen for.
     */
    public function broadcastAs(): string
    {
        return 'mission.offered';
    }
}
