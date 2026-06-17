<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\MissionPing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckPingTimeout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Order $order) {}

    public function handle(): void
    {
        $activePing = MissionPing::where('order_id', $this->order->id)
            ->where('status', 'pending')
            ->first();

        if ($activePing && now()->greaterThanOrEqualTo($activePing->expires_at)) {
            $activePing->update(['status' => 'expired']);

            // Re-trigger cascade dispatch loop to pick up the next closest target
            DispatchOrderCascade::dispatch($this->order);
        }
    }
}
