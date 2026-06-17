<?php

namespace App\Observers;

use App\Models\SubOrder;

class SubOrderObserver
{
    public function updated(SubOrder $subOrder): void
    {
        // Only trigger the sync if the status actually changed
        if ($subOrder->wasChanged('status')) {
            $subOrder->order->syncStatus();
        }
    }
}
