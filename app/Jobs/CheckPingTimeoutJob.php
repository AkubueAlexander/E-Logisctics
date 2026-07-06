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
            // Lock row and eager load relations to prevent race conditions and N+1 issues
            $ping = MissionPing::with('deliveryMission.order')
                ->lockForUpdate()
                ->find($this->pingId);

            // If the driver already clicked 'accepted' or 'rejected', stand down completely
            if (!$ping || $ping->status !== 'sent') {
                return;
            }

            // 1. Core State Transition: Mark this specific ping as timed out
            $ping->update([
                'status' => 'timed_out'
            ]);

            $mission = $ping->deliveryMission;

            // 2. Trigger the Next Cascade Pool Lookup
            // Using 'searching_for_driver' to match your AcceptOrder and DispatchOrderCascade flow
            if ($mission && $mission->status === 'searching_for_driver') {
                Log::info("HandlePingTimeout: Ping #{$ping->id} timed out. Re-triggering cascade loop for Order #{$mission->order->id}");
                
                // RE-TRIGGER DISPATCH ENGINE
                DispatchOrderCascade::dispatch($mission->order);
            }
        });
    }
}
