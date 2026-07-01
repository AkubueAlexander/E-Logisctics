<?php

namespace App\Listeners\Logistics;

use App\Events\DriverArrivedAtStore;
use App\Models\DeliveryMission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class HandleStoreArrival implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'logistics';
    public bool $afterCommit = true;

    public function handle(DriverArrivedAtStore $event): void
    {
        // 1. Extract the actual payload from the event class
        $subOrder = $event->subOrder;

        // 2. Resolve the delivery mission linked to this specific multi-vendor order container
        $deliveryMission = DeliveryMission::where('order_id', $subOrder->order_id)->first();

        // Safety Guard: Stop execution if no active mission envelope exists yet
        if (!$deliveryMission) {
            return;
        }

        // 3. Execute atomic database updates inside a safe transaction boundary
        DB::transaction(function () use ($subOrder, $deliveryMission) {

            // Update individual leg metadata so the store ledger registers arrival
            $subOrder->update([
                'status' => 'at_pickup',
                'arrived_at_vendor_at' => now()
            ]);

            // Update global mission status so tracking APIs can tell the customer
            $deliveryMission->update([
                'status' => 'at_pickup',
            ]);
        });

        // 4. Alert client frontend timelines over WebSockets using fully resolved properties
        // broadcast(new TimelineEventTriggered($deliveryMission->order_id, [
        //     'status' => 'courier_arrived',
        // ]));
    }
}
