<?php

namespace App\Jobs;

use App\Models\MissionPing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CheckPingTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public MissionPing $ping) {}

    public function handle(): void
    {
        DB::transaction(function () {
            // Re-fetch the ping to ensure we have the absolute latest status
            $currentPing = $this->ping->fresh();

            // If the driver already clicked accept or reject, the status will have changed.
            // We only care if it is still 'sent' after 30 seconds.
            if ($currentPing->status === 'sent') {

                // 1. Mark this specific ping as timed out
                $currentPing->update(['status' => 'timed_out']);

                // 2. We must immediately trigger the cascade to find the NEXT driver
                // The spatial query in PingNearestDriverJob will automatically exclude this timed-out driver
                \App\Jobs\PingNearestDriverJob::dispatch($currentPing->mission);
            }
        });
    }
}
