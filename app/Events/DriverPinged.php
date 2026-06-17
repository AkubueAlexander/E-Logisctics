<?php

namespace App\Events;

use App\Models\MissionPing;
use App\Models\DeliveryMission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverPinged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public MissionPing $ping,
        public DeliveryMission $mission
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcast strictly to the specific driver's private channel
        return [
            new PrivateChannel('driver.' . $this->ping->driver_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'mission.pinged';
    }

    /**
     * Get the data to broadcast.
     * We selectively map the payload so the mobile app gets exactly
     * what it needs to render the "New Request" modal without bloat.
     */
    public function broadcastWith(): array
    {
        // Extract the first store as the primary pickup origin
        $firstStore = $this->mission->order->subOrders->first()->store;

        return [
            'ping_id' => $this->ping->id,
            'mission_id' => $this->mission->id,
            'expires_at' => $this->ping->expires_at->toIso8601String(),
            'offer' => [
                'delivery_fee_minor_unit' => $this->mission->delivery_fee_minor_unit,
                'currency' => 'NGN',
            ],
            'pickup' => [
                'store_name' => $firstStore->name,
                'latitude' => $firstStore->latitude,
                'longitude' => $firstStore->longitude,
                'address' => $firstStore->address,
                'total_stops' => $this->mission->order->subOrders->count(), // E.g., "Pickup from 2 locations"
            ],
            'dropoff' => [
                'address' => $this->mission->order->delivery_address,
                'latitude' => $this->mission->order->latitude,
                'longitude' => $this->mission->order->longitude,
            ]
        ];
    }
}
