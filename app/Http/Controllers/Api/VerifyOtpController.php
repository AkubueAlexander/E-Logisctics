<?php

namespace App\Http\Controllers\Api;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class VerifyOtpController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        // 1. Fetch the OTP from cache
        $cachedOtp = Cache::get("email_otp:{$validated['email']}");

        if (!$cachedOtp || $cachedOtp !== $validated['otp']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired OTP.'
            ], 422);
        }

        // 2. Activate the user
        $user = User::where('email', $validated['email'])->first();
        $user->update([
            'email_verified_at' => now(),
            'is_active' => true
        ]);
        UserRegistered::dispatch($user);

        // 3. Clear the OTP so it can't be reused
        Cache::forget("email_otp:{$validated['email']}");



        return response()->json([
            'status' => 'success',
            'message' => 'Account verified successfully. You can now log in.'
        ], 200);
    }
}
