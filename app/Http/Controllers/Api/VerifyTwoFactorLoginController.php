<?php
namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class VerifyTwoFactorLoginController
{
    protected Google2FA $google2fa;

    public function __construct(Google2FA $google2fa)
    {
        $this->google2fa = $google2fa;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'two_factor_token' => ['required', 'string'],
            'code'             => ['required', 'digits:6'],
        ]);

        // 1. Fetch the cached login attempt
        $cacheKey = "2fa_login:{$request->two_factor_token}";
        $loginAttempt = Cache::get($cacheKey);

        if (!$loginAttempt) {
            return response()->json([
                'status' => 'error',
                'message' => 'The 2FA session has expired. Please log in again.'
            ], 422);
        }

        // 2. Find the user
        $user = User::find($loginAttempt['user_id']);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found.'], 404);
        }

        // 3. Verify the OTP code mathematically against their secret key
        $isValid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$isValid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid 2FA code.'
            ], 422);
        }

        // 4. Code is valid! Clear the temporary cache token
        Cache::forget($cacheKey);

        // 5. Issue the final authentication token (Same logic as standard login)
        $user->tokens()->where('name', $loginAttempt['device_name'])->delete();

        $tokenAbility = "role:{$user->system_role->value}";
        $token = $user->createToken($loginAttempt['device_name'], [$tokenAbility])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => $user
        ], 200);
    }
}
