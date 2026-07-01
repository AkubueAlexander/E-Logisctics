<?php

namespace App\Listeners\Notifications;

use App\Events\NewMissionOfferDispatched;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendDriverOfferNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int $tries = 2;
    public array $backoff = [2, 5];

    public function handle(NewMissionOfferDispatched $event): void
    {
        // Matches exact constructor types: $order, $driver, $timeoutSeconds
        $driver = $event->driver;
        $order = $event->order;

        if (empty($driver->device_token)) {
            return;
        }

        Http::withToken(config('services.fcm.key'))
            ->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $driver->device_token,
                'priority' => 'high',
                'notification' => [
                    'title' => '🚨 New Delivery Request!',
                    'body' => "Payout: " . number_format($order->delivery_fee_minor_unit / 100, 2) . " NGN. You have {$event->timeoutSeconds}s to accept."
                ],
                'data' => [
                    'order_id' => $order->id,
                ]
            ]);
    }
}
