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
            // Lock the order row to prevent race conditions during state transition
            $order = Order::where('id', $this->orderId)
                ->lockForUpdate()
                ->first();

            // Guard Clause: If a driver claimed it or it was manually handled, stand down
            if (!$order || $order->status !== 'searching_for_driver' || $order->driver_id !== null) {
                return;
            }

            Log::warning("OrderEngine: Global 30-minute dispatch window expired for Order #{$order->id}. Initiating auto-cancellation.");

            // 1. Transition the Parent Order State
            $order->update([
                'status' => 'cancelled'
            ]);

            // 2. Transition the Underlying Delivery Mission Wrapper
            $mission = $order->deliveryMission;
            if ($mission && $mission->status === 'searching_for_driver') {
                $mission->update([
                    'status' => 'timed_out' // Matches your strict migration enum values
                ]);
            }

            // 3. Financial Cleanup: Void any escrow/pending payout ledger lines
            // This prevents funds from getting permanently locked in limbo
            DB::table('ledgers')
                ->where('order_id', $order->id)
                ->where('status', 'pending')
                ->update([
                    'status'     => 'voided',
                    'updated_at' => now()
                ]);

            // 5. Broadcast to Unified Frontend Timeline Tracker
            event(new TimelineEventTriggered($order->id, [
                'status'  => 'cancelled',
                'message' => 'We could not find a nearby courier to fulfill your delivery. Your order has been cancelled and refunded.'
            ]));

            // 4. Push Live UI Notifications
             broadcast(new OrderAutoCancelledBySystem($order));
        });
    }
}
