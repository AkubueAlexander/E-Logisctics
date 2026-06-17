<?php

namespace App\Actions\Auth;

use App\Events\VerificationCodeCreated;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ResendOtpAction
{
    public function __invoke(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();

        // 1. Check if the user is already verified
        if ($user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account is already verified and active.',
            ]);
        }

        // 2. Prevent spam: Enforce a 60-second cooldown between requests
        $cooldownKey = "otp_cooldown:{$user->email}";
        if (Cache::has($cooldownKey)) {
            throw ValidationException::withMessages([
                'email' => 'Please wait a minute before requesting a new code.',
            ]);
        }

        // 3. Generate new 6-Digit Mobile Verification Code
        $otp = random_int(100000, 999999);

        // 4. Overwrite the old OTP cache for another 10 minutes
        Cache::put("email_otp:{$user->email}", $otp, now()->addMinutes(10));

        // 5. Set the 1-minute cooldown lock
        Cache::put($cooldownKey, true, now()->addMinute());


        // dispatchOtpNotification($user, $otp);
        VerificationCodeCreated::dispatch($user, (string)$otp, 'Password Reset');


    }
}
