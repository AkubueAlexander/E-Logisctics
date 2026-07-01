<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Events\OrderPaymentSuccessful;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveVerifyPaymentController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $txRef = $request->query('tx_ref');
        $transactionId = $request->query('transaction_id');

        if ($status !== 'successful' && $status !== 'completed') {
            return response()->json(['status' => 'failed', 'message' => 'Payment dropped at source.'], 400);
        }

        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->withoutVerifying()
            ->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify");

        if (!$response->successful() || $response->json('data.status') !== 'successful') {
            Log::warning('Flutterwave Redirect Verification Failed', ['tx_ref' => $txRef]);
            return response()->json(['status' => 'failed', 'message' => 'Verification failed.'], 422);
        }

        $order = Order::where('transaction_reference', $txRef)->firstOrFail();

        $apiAmount = $response->json('data.amount');
        $apiCurrency = $response->json('data.currency');

        $paidAmountMinor = intval($apiAmount * 100);

        if ($paidAmountMinor < $order->total_minor_unit || $apiCurrency !== 'NGN') {
            Log::error('Order payment mismatch flagged via Redirect.', ['order_id' => $order->id]);
            return response()->json(['status' => 'failed', 'message' => 'Amount mismatch.'], 400);
        }

        // Prevent double-processing
        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Payment already processed.'], 200);
        }

        // 1. Finalize the payment state
        $order->update(['payment_status' => 'paid']);

        // 2. Broadcast to the rest of the application
        OrderPaymentSuccessful::dispatch($order);


        return response()->json([
            'status' => 'success',
            'message' => 'Payment verified. Order is now processing.',
            'order_id' => $order->id,
        ], 200);
    }
}
