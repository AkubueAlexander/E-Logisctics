<?php

namespace App\Jobs;

use App\Models\MissionPing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class HandlePingTimeout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected int $pingId)
    {
        // We pass the ID instead of the full model to avoid stale model cache issues
        // when the queue worker wakes up 30 seconds later.
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            // Lock the specific ping row to prevent race conditions with a last-second acceptance
            $ping = MissionPing::where('id', $this->pingId)
                ->lockForUpdate()
                ->first();

            // If the driver already clicked 'accepted' or 'rejected', stand down completely
            if (!$ping || $ping->status !== 'sent') {
                return;
            }

            // 1. Core State Transition: Update status to your exact schema value
            $ping->update([
                'status' => 'timed_out'
            ]);

            // 2. Trigger the Next Cascade Pool Lookup
            // We pull the parent mission that is still marked as 'searching'
            $mission = $ping->deliveryMission;

            if ($mission && $mission->status === 'searching') {
                // RE-TRIGGER DISPATCH ENGINE:
                // Drop your existing PostGIS spatial discovery job back onto the queue here.
                // It will scan the 5km radius again, automatically filtering out this 'timed_out' driver.

                // Example connection:
                // DispatchOrderToDrivers::dispatch($mission->order);
            }
        });
    }
}
