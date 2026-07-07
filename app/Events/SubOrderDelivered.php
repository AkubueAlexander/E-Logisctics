<?php

namespace App\Events;

use App\Models\SubOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubOrderDeliveredEvent
{
    use Dispatchable, SerializesModels;

    public SubOrder $subOrder;
    public bool $masterOrderCompleted;

    /**
     * Create a new event instance to transport immutable context to async processors.
     */
    public function __construct(SubOrder $subOrder, bool $masterOrderCompleted)
    {
        $this->subOrder = $subOrder;
        $this->masterOrderCompleted = $masterOrderCompleted;
    }
}
