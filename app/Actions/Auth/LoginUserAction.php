<?php

namespace App\Actions\Auth;

use App\DataTransferObjects\LoginDTO;
use App\Events\UserRegistered;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    public function execute(LoginDTO $dto): array
    {
        // 1. Fetch the user for login (O(1) search via Indexed Email Column)
        $user = User::where('email', $dto->email)->first();

        // 2. Validate standard credentials (cryptographic timing-attack safe verification)
        if (! $user || ! Hash::check($dto->password, $user->password)) {
            AuditLogger::log('failed_login_attempt', null, ['attempted_email' => $dto->email]);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // 3. (Domain Requirement) Check Email Verification
        if (is_null($user->email_verified_at)) {
            return ['status' => 'unverified'];
        }

        // 4. (Security Requirement) Handle Two-Factor Authentication
        if ($user->two_factor_enabled) {
            $twoFactorToken = Str::random(64);

            // Track who is trying to log in for the next step (valid for 5 mins)
            Cache::put("2fa_login:{$twoFactorToken}", [
                'user_id' => $user->id,
                'device_name' => $dto->device_name, // Fixed to standard snake_case
            ], now()->addMinutes(5));

            return [
                'status' => 'requires_2fa',
                'two_factor_token' => $twoFactorToken,
            ];
        }

        // 5. Standard Login Flow: Clear existing tokens for the specific device, then issue a new one
        $user->tokens()->where('name', $dto->device_name)->delete(); // Fixed to standard snake_case

        $tokenAbility = "role:{$user->system_role->value}";
        $token = $user->createToken($dto->device_name, [$tokenAbility])->plainTextToken; // Fixed to standard snake_case

        // 6. Fire Security Audit Event
        AuditLogger::log('successful_login', $user);



        return [
            'status' => 'success',
            'user' => $user,
            'token' => $token,
        ];
    }
}
