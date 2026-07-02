<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;
use App\Models\Order;


Broadcast::channel('drivers.{id}', function ($user, $id) {

    return (int) $user->id === (int) $id;
});

Broadcast::channel('App.Models.Order.{orderId}.Tracking', function (User $user, int $orderId) {
    $order = Order::find($orderId);

    if (!$order) {
        return false;
    }

    return (int) $user->id === (int) $order->customer_id || (int) $user->id === (int) $order->driver_id;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
