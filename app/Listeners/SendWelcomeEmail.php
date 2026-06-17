<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\WelcomeUserMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

// 'ShouldQueue' is the magic interface that tells Laravel to push this to Redis
class SendWelcomeEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * INTELLIGENCE 1: Queue Isolation
     * Push this to a dedicated 'emails' queue in Redis, so heavy video uploads
     * or data exports don't delay your transactional emails.
     */
    public string $queue = 'emails';

    /**
     * INTELLIGENCE 2: Transaction Awareness
     * If the queue worker is faster than your database committing the transaction,
     * it might try to send an email for a user that doesn't "exist" yet.
     * This forces Redis to wait until the DB transaction is fully saved.
     */
    public bool $afterCommit = true;

    /**
     * INTELLIGENCE 3: Resiliency
     * If Mailtrap/Sendgrid fails, try 3 times before giving up.
     */
    public int $tries = 3;

    /**
     * Wait 10, 20, then 30 seconds between retries to give the external SMTP service time to recover.
     */
    public array $backoff = [10, 20, 30];

    public function handle(UserRegistered $event): void
    {
        // Send the email
        Mail::to($event->user->email)->send(new WelcomeUserMail($event->user));
    }

    /**
     * INTELLIGENCE 4: Graceful Failure Handling
     * If it fails all 3 times, log it so the admin knows.
     */
    public function failed(UserRegistered $event, \Throwable $exception): void
    {
        Log::error('Failed to send welcome email.', [
            'user_id' => $event->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
