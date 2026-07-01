<?php

namespace App\Listeners\Dispatch;

use App\Events\OrderReadyForDispatch;
use App\Models\DeliveryMission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class InitializeDeliveryMission implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'dispatch';
    public bool $afterCommit = true;
    public int $tries = 3;

    public function handle(OrderReadyForDispatch $event): void
    {
        $order = $event->order; // Matches: public Order $order

        DeliveryMission::create([
            'order_id' => $order->id,
            'status' => 'searching',
            'delivery_fee_minor_unit' => $order->delivery_fee_minor_unit,
        ]);
    }

    public function failed(OrderReadyForDispatch $event, \Throwable $exception): void
    {
        Log::critical("Dispatch Engine Failure for Order #{$event->order->id}", [
            'error' => $exception->getMessage()
        ]);
    }
}
