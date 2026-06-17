<?php

namespace App\Http\Controllers\Api;

use App\Events\VerificationCodeCreated;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController
{
    public function sendResetOtp(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email', 'exists:users,email']]);

        $user = User::where('email', $request->email)->first();

        $otp = random_int(100000, 999999);
        Cache::put("password_reset_otp:{$request->email}", $otp, now()->addMinutes(15));

        VerificationCodeCreated::dispatch($user, (string)$otp, 'Password Reset');

        return response()->json([
            'status' => 'success',
            'message' => 'A password recovery verification OTP has been dispatched.'
        ], 200);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed']
        ]);

        $cachedOtp = Cache::get("password_reset_otp:{$validated['email']}");

        if (!$cachedOtp || $cachedOtp !== $validated['otp']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired password reset verification OTP.'
            ], 422);
        }

        $user = User::where('email', $validated['email'])->first();
        $user->password = $validated['password']; // Handled safely by 'hashed' cast on model
        $user->save();

        Cache::forget("password_reset_otp:{$validated['email']}");

        return response()->json([
            'status' => 'success',
            'message' => 'Your account security credentials have been updated. Please log in.'
        ], 200);
    }
}
