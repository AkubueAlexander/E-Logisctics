<?php

namespace App\Actions\Payment;

use App\Models\Order;
use App\Events\OrderPaymentSuccessful;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCustomerPayment
{
    public function execute(array $transactionData): void
    {
        DB::transaction(function () use ($transactionData) {
            $order = Order::where('transaction_reference', $transactionData['tx_ref'])
                ->lockForUpdate()
                ->first();

            if (!$order) {
                Log::error('Webhook received for unknown Order TX Ref: ' . ($transactionData['tx_ref'] ?? 'unknown'));
                return;
            }

            if ($order->payment_status === 'paid') {
                return; // Idempotency Guard
            }

            $paidAmountMinor = intval($transactionData['amount'] * 100);
            if ($paidAmountMinor < $order->total_minor_unit || $transactionData['currency'] !== 'NGN') {
                Log::error('Order payment mismatch flagged via Webhook.', ['order_id' => $order->id]);
                return;
            }

            if ($transactionData['status'] === 'successful') {
                $order->update(['payment_status' => 'paid']);

                OrderPaymentSuccessful::dispatch($order);

                Log::info("Order {$order->id} paid successfully via Action Pipeline.");
            }
        });
    }
}
