<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InitializePaymentController extends Controller
{
    /**
     * Initialize Flutterwave standard checkout link for an order.
     */
    public function initialize(Request $request, $order_id): JsonResponse
    {
        $user = $request->user();

        // 1. Fetch the order ensuring it belongs to the authenticated customer
        $order = Order::where('id', $order_id)
            ->where('customer_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // 2. Safeguard: Check if it's already paid
        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'This order has already been paid for.'], 400);
        }

        // 3. Flutterwave expects major units (e.g., Naira), so convert from minor units (Kobo)
        $amountInNaira = $order->total_minor_unit / 100;

        // 4. Ping Flutterwave's Standard Payment API endpoint
        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->withoutVerifying()
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $order->transaction_reference,
                'amount' => $amountInNaira,
                'currency' => 'NGN',
                'redirect_url' => url('/api/v1/payments/verify-payment'),
                'customer' => [
                    'email' => $user->email,
                    'name'  => $user->name ?? 'LogiFlow Customer',
                ],
                'customizations' => [
                    'title'       => 'LogiFlow Delivery',
                    'description' => "Payment for Order #{$order->id}",
                ]
            ]);

        // 5. If Flutterwave successfully gives us a link, return it to Postman
        if ($response->successful() && $response->json('status') === 'success') {
            return response()->json([
                'message'      => 'Payment link generated successfully.',
                'order_id'     => $order->id,
                'tx_ref'       => $order->transaction_reference,
                'amount_naira' => $amountInNaira,
                'payment_link' => $response->json('data.link') // <--- This is what you click in Postman
            ], 200);
        }

        // Log any API failures for debug visibility
        Log::error('Flutterwave Initialization API Failure', [
            'order_id' => $order->id,
            'response' => $response->json()
        ]);

        return response()->json([
            'message' => 'Unable to initialize payment gateway at this time.',
            'error'   => $response->json('message') ?? 'Gateway Error'
        ], 500);
    }
}
