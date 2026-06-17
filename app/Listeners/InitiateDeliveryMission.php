<?php

namespace App\Listeners;

use App\Events\OrderReadyForDispatch;
use App\Models\DeliveryMission;
use App\Jobs\PingNearestDriverJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class InitiateDeliveryMission implements ShouldQueue
{
    public function handle(OrderReadyForDispatch $event): void
    {
        $order = $event->order;

        // 1. Create the master Delivery Mission wrapper
        $mission = DeliveryMission::create([
            'order_id' => $order->id,
            'status' => 'searching',
            'delivery_fee_minor_unit' => $order->delivery_fee_minor_unit,
        ]);

        Log::info("Delivery Mission #{$mission->id} initialized for Order #{$order->id}");

        // 2. Dispatch the first cycle of our Cascade Loop
        PingNearestDriverJob::dispatch($mission);
    }
}
