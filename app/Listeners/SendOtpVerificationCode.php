<?php

namespace App\Listeners;


use App\Events\VerificationCodeCreated;
use App\Mail\SendOtpMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

// 'ShouldQueue' is the magic interface that tells Laravel to push this to Redis
class SendOtpVerificationCode implements ShouldQueue
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
     * it might try to process this for a user that doesn't "exist" yet.
     * This forces Redis to wait until the DB transaction is fully saved.
     */
    public bool $afterCommit = true;

    /**
     * INTELLIGENCE 3: Resiliency
     * If Mailtrap/Sendgrid fails, try 3 times before giving up.
     */
    public int $tries = 3;

    /**
     * Wait 5, 10, then 15 seconds between retries.
     * Note: Kept slightly shorter than welcome emails since OTPs are highly time-sensitive.
     */
    public array $backoff = [5, 10, 15];

    public function handle(VerificationCodeCreated $event): void
    {


        // 3. Send the OTP email
        Mail::to($event->user->email)->send(new SendOtpMail($event->user,$event->otp,$event->context ));
    }

    /**
     * INTELLIGENCE 4: Graceful Failure Handling
     * If it fails all 3 times, log it so the admin knows.
     */
    public function failed(VerificationCodeCreated $event, \Throwable $exception): void
    {
        Log::error('Failed to send OTP verification email.', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
