<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerDeliveryOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Public properties are automatically available in the Blade view.
     */
    public int $otp;
    public Order $order;

    /**
     * Create a new message instance.
     */
    public function __construct(int $otp, Order $order)
    {
        $this->otp = $otp;
        $this->order = $order;
    }

    /**
     * Get the message envelope (Subject, headers, etc.)
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Secure Delivery Code for Order #{$this->order->id}",
        );
    }

    /**
     * Get the message content definition (View template)
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.customer_delivery_otp',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
