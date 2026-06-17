<?php

namespace App\Listeners;

use App\Events\OrderPaymentSuccessful;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class TransitionOrderState implements ShouldQueue
{
    public function handle(OrderPaymentSuccessful $event): void
    {
        $order = $event->order;

        DB::transaction(function () use ($order) {
            // Log the transition history
            $order->stateTransitions()->create([
                'from_status' => $order->status,
                'to_status'   => 'pending_acceptance',
                'triggered_by' => 'payment_success',
                'metadata'     => [
                    'gateway'      => 'flutterwave',
                    'attempt_id'   => $order->transaction_reference,
                    'ip_address'   => request()->ip() // Contextual info that doesn't need a column
                ]
            ]);

            // Update the main order status
            $order->update(['status' => 'pending_acceptance']);

            // Update all vendor sub-orders
            $order->subOrders()->update(['status' => 'pending_acceptance']);
        });
    }
}
