<?php

namespace App\Jobs;

use App\Events\OrderAutoCancelledBySystem;
use App\Events\Tracking\TimelineEventTriggered;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelUnassignedOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected int $orderId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            // Lock the parent order row to prevent race conditions during state transition
            $order = Order::where('id', $this->orderId)
                ->lockForUpdate()
                ->first();

            // Guard Clause: If a driver claimed it or it was manually handled, stand down
            if (!$order || $order->status !== 'searching_for_driver' || $order->driver_id !== null) {
                return;
            }

            Log::warning("OrderEngine: Global 30-minute dispatch window expired for Order #{$order->id}. Initiating auto-cancellation.");

            $fromStatus = $order->status;
            $toStatus = 'cancelled';

            // 1. Transition the Parent Order State
            $order->update([
                'status' => $toStatus
            ]);

            DB::table('order_state_transitions')->insert([
                'order_id'             => $order->id,
                'from_status'          => $fromStatus,
                'to_status'            => $toStatus,
                'triggered_by_user_id' => null,
                'metadata'             => json_encode(['reason' => 'Dispatch window expired, no driver found.']),
                'created_at'           => now(),
            ]);

            // 2. Transition child Sub-Orders to kill active merchant dashboard streams
            DB::table('sub_orders')
                ->where('order_id', $order->id)
                ->whereIn('status', ['pending_acceptance', 'accepted']) // Target active pre-fulfillment states
                ->update([
                    'status'     => 'cancelled',
                    'updated_at' => now()
                ]);

            // 3. Transition the Underlying Delivery Mission Wrapper
            $mission = $order->deliveryMission;
            if ($mission && $mission->status === 'searching_for_driver') {
                $mission->update([
                    'status' => 'timed_out'
                ]);
            }

            // 4. Financial Processing: Fetch ALL original checkout payment ledgers
            $originalLedgers = DB::table('ledgers')
                ->where('order_id', $order->id)
                ->where('transaction_type', 'payment') // Adjust if your original type name differs
                ->get();

            if ($originalLedgers->isNotEmpty()) {
                $totalRefundMinorUnit = 0;

                foreach ($originalLedgers as $ledger) {
                    // Write a distinct reversing ledger line item per sub-order entry for accounting clean-up
                    DB::table('ledgers')->insert([
                        'order_id'          => $order->id,
                        'sub_order_id'      => $ledger->sub_order_id,
                        'transaction_type'  => 'refund',
                        'store_id'          => $ledger->store_id,
                        'user_id'           => $ledger->user_id,
                        'amount_minor_unit' => $ledger->amount_minor_unit,
                        'currency_code'     => $ledger->currency_code,
                        'status'            => 'completed',
                        'created_at'        => now(),
                        'updated_at'        => now()
                    ]);

                    $totalRefundMinorUnit += $ledger->amount_minor_unit;
                }

                // 5. Atomic Wallet Balance & Wallet Transaction Synchronization
                // Lock the wallet row to prevent balance write race conditions
                $wallet = DB::table('wallets')
                    ->where('owner_type', 'App\Models\User')
                    ->where('owner_id', $order->customer_id)
                    ->lockForUpdate()
                    ->first();

                if ($wallet) {
                    $newRunningBalance = $wallet->balance_minor_unit + $totalRefundMinorUnit;

                    // Safely update running total balance on the profile exactly once
                    DB::table('wallets')
                        ->where('id', $wallet->id)
                        ->update([
                            'balance_minor_unit' => $newRunningBalance,
                            'updated_at'         => now()
                        ]);

                    // Append a single consolidated audit line item to match the wallet balance shift
                    DB::table('wallet_transactions')->insert([
                        'wallet_id'         => $wallet->id,
                        'type'              => 'credit',
                        'amount_minor_unit' => $totalRefundMinorUnit,
                        'running_balance'   => $newRunningBalance,
                        'description'       => "Automated refund for timeout on Order #{$order->id}",
                        'reference'         => 'REF-' . ($order->transaction_reference ?? uniqid()),
                        'created_at'        => now(),
                        'updated_at'        => now()
                    ]);
                }
            }

            // 6. Broadcast to Unified Frontend Timeline Tracker
            event(new TimelineEventTriggered($order->id, [
                'status'  => 'cancelled',
                'message' => 'We could not find a nearby courier to fulfill your delivery. Your order has been cancelled and refunded.'
            ]));

            // 7. Push Live UI Notifications
            broadcast(new OrderAutoCancelledBySystem($order));
        });
    }
}
