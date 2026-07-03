<?php

namespace App\Actions\Driver;

use App\Jobs\DispatchOrderCascade;
use App\Models\MissionPing;
use Illuminate\Support\Facades\DB;

class RejectOrder
{
    public function execute(MissionPing $ping): void
    {
        DB::transaction(function () use ($ping) {
            // Re-verify the ping is still active before executing mutation
            $currentPing = $ping->fresh();

            if ($currentPing && $currentPing->status === 'sent') {

                // 1. Mark this specific ping as rejected
                $currentPing->update(['status' => 'rejected']);

                // 2. Fetch the parent delivery mission and order context
                $mission = $currentPing->deliveryMission;

                // 3. If the mission is still actively looking for a rider, cascade instantly
                if ($mission && $mission->status === 'searching') {
                    DispatchOrderCascade::dispatch($mission->order);
                }
            }
        });
    }
}
