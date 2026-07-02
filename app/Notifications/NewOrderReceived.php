<?php

namespace App\Notifications;

use App\Models\SubOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class NewOrderReceived extends Notification
{
    use Queueable;

    

    public function __construct(public SubOrder $subOrder)
    {
        
    }

    public function via($notifiable): array
    {
        // Add 'broadcast' here later for real-time app pings
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return [
            'sub_order_id' => $this->subOrder->id,
            'message'      => "New order received! Please prepare food for Order #{$this->subOrder->order_id}.",
            'amount'       => $this->subOrder->subtotal_minor_unit,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'sub_order_id' => $this->subOrder->id,
            'message'      => "New order received! Please prepare food for Order #{$this->subOrder->order_id}.",
            'amount'       => $this->subOrder->subtotal_minor_unit,
        ]);
    }

    
}
