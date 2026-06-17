<?php

namespace App\Http\Controllers\Api\Payment;

use App\Events\OrderPaymentSuccessful;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FlutterwaveWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // 1. Verify the Webhook Signature (Crucial for Security)
        $signature = $request->header('verif-hash');
        $localSecretHash = env('FLW_SECRET_HASH');

        if (!$signature || $signature !== $localSecretHash) {
            Log::warning('Unauthorized Flutterwave Webhook Attempt', ['ip' => $request->ip()]);
            return response()->json(['status' => 'unauthorized'], 401);
        }

        // 2. Parse the payload
        $payload = $request->all();

        // We only care about successful charges right now
        if (!isset($payload['event']) || $payload['event'] !== 'charge.completed') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $transactionData = $payload['data'];

        // 3. Find the Order using the tx_ref sent by the frontend during checkout
        $order = Order::where('transaction_reference', $transactionData['tx_ref'])->first();

        if (!$order) {
            Log::error('Webhook received for unknown Order TX Ref: ' . $transactionData['tx_ref']);
            return response()->json(['status' => 'order_not_found'], 404);
        }

        // 4. Verify the amount matches (Flutterwave amounts are in Major units, our DB is in Minor units)
        // e.g. Flutterwave sends 2000 (Naira). Our DB has 200000 (Kobo).
        $paidAmountMinor = intval($transactionData['amount'] * 100);

        if ($paidAmountMinor < $order->total_minor_unit) {
            Log::error('Order partially paid via Webhook.', [
                'order_id' => $order->id,
                'expected' => $order->total_minor_unit,
                'received' => $paidAmountMinor
            ]);
            // Handle partial payment logic here if needed
            return response()->json(['status' => 'partial_payment_flagged'], 200);
        }

        // 5. Process the Success inside a Transaction
        if ($transactionData['status'] === 'successful' && $order->payment_status !== 'paid') {
            // 1. Mark as paid
            $order->update(['payment_status' => 'paid']);

            // 2. Let our Event system do all the heavy lifting (Ledgers, Notifications, Statuses)
            OrderPaymentSuccessful::dispatch($order);

            Log::info("Order {$order->id} paid successfully via Flutterwave Webhook.");
        }

        // Flutterwave requires a 200 OK response so they don't keep retrying the webhook
        return response()->json(['status' => 'success'], 200);
    }
}
