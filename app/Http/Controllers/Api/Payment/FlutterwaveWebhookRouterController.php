<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Actions\Payment\ProcessCustomerPayment;
use App\Actions\Financial\ProcessPayoutSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FlutterwaveWebhookRouterController extends Controller
{
    public function __invoke(
        Request $request,
        ProcessCustomerPayment $processCustomerPayment,
        ProcessPayoutSettlement $processPayoutSettlement
    ): JsonResponse {
        // 1. Cryptographic Signature Validation Guard
        $signature = $request->header('verif-hash');
        $localSecretHash = config('services.flutterwave.webhook_hash');

        if (!$signature || $signature !== $localSecretHash) {
            Log::warning('Unauthorized Flutterwave Webhook Attempt Detained', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Signature verification failed.'], 401);
        }

        $event = $request->input('event');
        $payload = $request->all();

        try {
            // 2. The Traffic Controller Switch
            match ($event) {
                'charge.completed' => $processCustomerPayment->execute($payload['data'] ?? []),

                'transfer.completed', 'transfer.failed' => $processPayoutSettlement->execute($event, $payload['data'] ?? []),

                default => Log::info("Unhandled Flutterwave event type received: {$event}"),
            };

            // 3. Always return a 200 OK so Flutterwave stops retrying
            return response()->json(['status' => 'acknowledged'], 200);

        } catch (\Exception $e) {
            Log::critical("System Degradation during Webhook Routing Execution", [
                'event' => $event,
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Internal processing error.'], 500);
        }
    }
}
