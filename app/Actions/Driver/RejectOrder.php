<?php

namespace App\Actions\Driver;

use App\Models\MissionPing;
use App\Jobs\DispatchOrderToDrivers;
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
                    DispatchOrderToDrivers::dispatch($mission->order);
                }
            }
        });
    }
}
