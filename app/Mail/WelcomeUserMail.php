<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeUserMail extends Mailable
{
    // SerializesModels ensures only the User ID is sent to Redis,
    // and the fresh user model is pulled from the DB when the email actually sends.
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to the Platform!',
        );
    }

    public function content(): Content
    {
        // You will need to create a simple blade file at resources/views/emails/welcome.blade.php
        return new Content(
            view: 'emails.welcome',
        );
    }
}
