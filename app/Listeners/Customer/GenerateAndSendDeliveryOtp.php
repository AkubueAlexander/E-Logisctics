<?php
namespace App\Listeners\Customer;

use App\Events\OrderInTransit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\CustomerDeliveryOtpMail;

class GenerateAndSendDeliveryOtp implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(OrderInTransit $event): void
    {
        $order = $event->order;

        // 1. Generate a cryptographically secure 6-digit OTP
        $otp = random_int(100000, 999999);

        // 2. Store strictly in Redis with a 2-hour TTL
        // Using a clear naming convention for the key prevents collisions
        $cacheKey = "delivery_otp:order:{$order->id}";
        Cache::store('redis')->put($cacheKey, $otp, now()->addHours(2));

        Log::info("Testing OTP for Order #{$order->id}: {$otp}");

        // 3. Dispatch the Email to the Customer

        Mail::to($order->customer->email)->send(new CustomerDeliveryOtpMail($otp, $order));
    }
}
