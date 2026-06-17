<?php

namespace App\Notifications;

use App\Models\SubOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class NewOrderReceived extends Notification
{
    use Queueable;

    public SubOrder $subOrder;

    public function __construct(SubOrder $subOrder)
    {
        $this->subOrder = $subOrder;
    }

    public function via($notifiable): array
    {
        // Add 'broadcast' here later for real-time app pings
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'sub_order_id' => $this->subOrder->id,
            'message'      => "New order received! Please prepare food for Order #{$this->subOrder->order_id}.",
            'amount'       => $this->subOrder->subtotal_minor_unit,
        ];
    }
}
