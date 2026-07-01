<?php

namespace App\Events;

use App\Models\SubOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverArrivedAtStore implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SubOrder $subOrder) {}

    public function broadcastOn(): array
    {
        // Broadcasts directly to the specific vendor's dashboard
        return [
            new PrivateChannel("App.Models.Store.{$this->subOrder->store_id}")
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'sub_order_id' => $this->subOrder->id,
            'status'       => 'driver_arrived',
            'message'      => 'The courier has arrived at your location for pickup.'
        ];
    }
}
