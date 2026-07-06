<?php

namespace App\Jobs;

use App\Models\MissionPing;
use App\Jobs\DispatchOrderCascade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckPingTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    // Accept integer ID instead of the full model to prevent ModelNotFound exceptions
    public function __construct(protected int $pingId) {}

    public function handle(): void
    {
        // Use database transactions and locks to prevent race conditions if a driver accepts right at the deadline
        DB::transaction(function () {

            // Query using the passed ID, lock the row, and eager load relations
            $freshPing = MissionPing::with('deliveryMission.order')
                ->lockForUpdate()
                ->find($this->pingId);

            // Guard Clause: If the ping was deleted, accepted, or manually rejected, step away immediately
            if (!$freshPing || $freshPing->status !== 'sent') {
                return;
            }

            // 1. Permanently invalidate this specific ping record
            $freshPing->update(['status' => 'timed_out']);

            $mission = $freshPing->deliveryMission;
            $order = $mission->order;

            Log::info("DispatchTimeout: Ping #{$freshPing->id} for Order #{$order->id} timed out. Re-entering cascade.");

            // 2. Seamlessly trigger the matching engine loop to pick up the next candidate
            DispatchOrderCascade::dispatch($order);
        });
    }
}
