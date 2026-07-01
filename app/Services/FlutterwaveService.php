<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Exception;

class FlutterwaveService
{
    /**
     * Generates a hosted payment link for a given order.
     */
    public function generatePaymentLink(Order $order, User $user): string
    {
        $amountInNaira = $order->total_minor_unit / 100;

        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->withoutVerifying()
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref'       => $order->transaction_reference,
                'amount'       => $amountInNaira,
                'currency'     => 'NGN',
                'redirect_url' => route('payment.verify'), // Make sure this route is named
                'customer'     => [
                    'email' => $user->email,
                    'name'  => $user->name,
                ],
                'customizations' => [
                    'title'       => 'Q-Commerce Logistics Replica Checkout',
                    'description' => "Payment for Order #{$order->id}",
                ]
            ]);

        if (!$response->successful() || $response->json('status') !== 'success') {
            throw new Exception('Flutterwave initialization failed: ' . $response->body());
        }

        return $response->json('data.link');
    }
}
