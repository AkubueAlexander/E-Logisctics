<?php

namespace App\Events;

use App\Models\Order;
use App\Models\User;
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
        public int $timeoutSeconds = 60
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
        // Map all unique stores attached to the sub-orders matching the stores table schema
        $pickups = $this->order->subOrders->map(function ($subOrder) {
            $store = $subOrder->store;
            return [
                'sub_order_id' => $subOrder->id,
                'store_name'   => $store?->name,
                'latitude'     => $store?->latitude,
                'longitude'    => $store?->longitude,
                'address'      => $store?->address,
            ];
        })->unique('store_name')->values()->toArray();

        return [
            'order_id'               => $this->order->id,
            'delivery_fee_formatted' => number_format($this->order->delivery_fee_minor_unit / 100, 2),
            'currency'               => $this->order->currency_code, 
            'timeout_seconds'        => $this->timeoutSeconds,
            'total_stops'            => count($pickups) + 1,
            'pickups'                => $pickups,
            'dropoff' => [
                'address'   => $this->order->snapshot_delivery_address,   
                'latitude'  => $this->order->snapshot_delivery_latitude,  
                'longitude' => $this->order->snapshot_delivery_longitude, 
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
