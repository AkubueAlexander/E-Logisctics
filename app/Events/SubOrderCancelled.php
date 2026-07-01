<?php

namespace App\Events;

use App\Models\SubOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubOrderCancelled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     * Granular EDA Design: Target exactly one failing sub-order at a time.
     */
    public function __construct(
        public SubOrder $subOrder
    ) {}
}
