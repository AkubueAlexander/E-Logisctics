<?php

namespace App\Listeners;

use App\Events\OrderPaymentSuccessful;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class DistributePaymentToLedgers implements ShouldQueue
{
    public function handle(OrderPaymentSuccessful $event): void
    {
        $order = $event->order;
        $order->load('subOrders');

        DB::transaction(function () use ($order) {

            $order->ledgers()->create([
                'sub_order_id'      => null,
                'user_id'           => $order->customer_id,
                'transaction_type'  => 'customer_charge',
                'amount_minor_unit' => $order->total_minor_unit,
                'status'            => 'completed'
            ]);

            // 1. Process SubOrders (Record Store Payouts + Platform Commissions into Escrow)
            foreach ($order->subOrders as $subOrder) {
                $storePayout = $subOrder->subtotal_minor_unit - $subOrder->platform_commission_minor_unit;

                // A. Audit Ledger: Record Store Payout as PENDING
                $order->ledgers()->create([
                    'sub_order_id'      => $subOrder->id,
                    'store_id'          => $subOrder->store_id,
                    'transaction_type'  => 'store_payout',
                    'amount_minor_unit' => $storePayout,
                    'status'            => 'pending' // Stays pending until successful delivery
                ]);

                // B. Audit Ledger: Record Platform Commission as COMPLETED
                $order->ledgers()->create([
                    'sub_order_id'      => $subOrder->id,
                    'transaction_type'  => 'platform_commission',
                    'amount_minor_unit' => $subOrder->platform_commission_minor_unit,
                    'status'            => 'completed'
                ]);
            }

            // 2. Record Platform Service Fee as PENDING
            if ($order->service_fee_minor_unit > 0) {
                $order->ledgers()->create([
                    'transaction_type'  => 'service_fee_revenue',
                    'amount_minor_unit' => $order->service_fee_minor_unit,
                    'status'            => 'completed'
                ]);
            }

            // 3. Record Delivery Fee as PENDING (Escrow for the future rider)
            if ($order->delivery_fee_minor_unit > 0) {
                $order->ledgers()->create([
                    'transaction_type'  => 'delivery_escrow',
                    'amount_minor_unit' => $order->delivery_fee_minor_unit,
                    'status'            => 'pending'
                ]);
            }
        });
    }
}
