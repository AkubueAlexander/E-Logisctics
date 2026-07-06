<?php

namespace App\Listeners\Logistics;

use App\Events\DriverArrivedAtStore;
use App\Events\Tracking\TimelineEventTriggered;
use App\Models\DeliveryMission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleStoreArrival implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'logistics';
    public bool $afterCommit = true;

    public function handle(DriverArrivedAtStore $event): void
    {
        $subOrder = $event->subOrder;

        // 1. Resolve the delivery mission linked to this specific multi-vendor order container
        $deliveryMission = DeliveryMission::where('order_id', $subOrder->order_id)->first();

        // Safety Guard: Stop execution if no active mission envelope exists yet
        if (!$deliveryMission) {
            return;
        }

        // 2. Update global mission status so tracking APIs can tell the customer
        $deliveryMission->update([
            'status' => 'at_pickup',
        ]);

        // 3. Alert client frontend timelines over WebSockets using fully resolved properties
        broadcast(new TimelineEventTriggered($deliveryMission->order_id, [
            'status' => 'courier_arrived',
        ]));
    }
}