<?php

namespace App\Listeners;

use App\Events\OrderPaymentSuccessful;
use App\Notifications\NewOrderReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyMerchantOfNewOrder implements ShouldQueue
{
    public function handle(OrderPaymentSuccessful $event): void
    {
        $order = $event->order;
        $order->load('subOrders.store.users');

        foreach ($order->subOrders as $subOrder) {
            // 1. Fetch only the users who are either 'owner' or 'manager' for this specific store
            $managers = $subOrder->store->users()
                ->wherePivotIn('role', ['owner', 'manager'])
                ->get();

            // 2. Send the notification ONCE to the entire collection of found users
            if ($managers->isNotEmpty()) {
                Notification::send($managers, new NewOrderReceived($subOrder));
            }
        }
    }
}
