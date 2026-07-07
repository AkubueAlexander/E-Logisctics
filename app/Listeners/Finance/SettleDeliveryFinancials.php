<?php

namespace App\Listeners\Finance;

use App\Events\SubOrderDeliveredEvent;
use App\Models\Ledger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class SettleDeliveryFinancials implements ShouldQueue
{
    /**
     * Route this financial work strictly onto your high-performance Redis queue cluster.
     */
    public string $connection = 'redis';

    /**
     * The targeted queue name within Redis for accounting tasks.
     */
    public string $queue = 'financials';

    /**
     * Process heavy double-entry bookkeeping actions asynchronously.
     */
    public function handle(SubOrderDelivered $event): void
    {
        $subOrder = $event->subOrder;
        $order = $subOrder->order;

        // Wrap accounting mutations in an isolated transaction to prevent balance anomalies
        DB::transaction(function () use ($subOrder, $order, $event) {

            // 1. SETTLE VENDOR FINANCIALS
            // Transition escrow lines tied directly to this sub-order fulfillment block
            $escrowLines = Ledger::where('sub_order_id', $subOrder->id)
                ->where('transaction_type', 'store_payout')
                ->where('status', 'pending')
                ->get();

            foreach ($escrowLines as $ledgerLine) {
                $ledgerLine->update([
                    'status'     => 'completed',
                    'updated_at' => now()
                ]);

                // Increment Vendor Store Wallet Balance directly
                if ($subOrder->store && $subOrder->store->wallet) {
                    $subOrder->store->wallet->increment(
                        'balance_minor_unit',
                        $ledgerLine->amount_minor_unit
                    );
                }
            }

            // 2. SETTLE DRIVER FINANCIALS
            // Trigger driver payment logic only when the last sub-order drops, closing the master trip
            if ($event->masterOrderCompleted) {

                // Establish an immutable audit record for the driver's payouts
                Ledger::create([
                    'order_id'          => $order->id,
                    'sub_order_id'      => null,
                    'transaction_type'  => 'driver_payout',
                    'store_id'          => null,
                    'user_id'           => $order->driver_id,
                    'amount_minor_unit' => $order->delivery_fee_minor_unit,
                    'currency_code'     => 'NGN',
                    'status'            => 'completed',
                ]);

                // Unlock the delivery payout into the active driver wallet mapping
                if ($order->driver && $order->driver->wallet) {
                    $order->driver->wallet->increment(
                        'balance_minor_unit',
                        $order->delivery_fee_minor_unit
                    );
                }
            }
        });
    }
}
