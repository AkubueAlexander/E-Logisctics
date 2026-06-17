<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController
{
    protected Google2FA $google2fa;

    /**
     * Senior Tip: Inject the service via the constructor.
     * Laravel automatically resolves this from the Service Container.
     */
    public function __construct(Google2FA $google2fa)
    {
        $this->google2fa = $google2fa;
    }

    /**
     * Step 1: Generate the Secret Key for Google Authenticator.
     * Accessible only via an authenticated session.
     */


    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Generate a unique secret key
        $secretKey = $this->google2fa->generateSecretKey();

        // 2. Generate Recovery Codes (e.g., 8 codes, formatted as xxxxx-xxxxx)
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = Str::random(5) . '-' . Str::random(5);
        }

        // 3. Save to user account (Stays disabled until confirmed)
        $user->forceFill([
            'two_factor_secret' => $secretKey,
            'two_factor_enabled' => false,
            // Encrypt the JSON array before storing it in the database for security
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        // 4. Create the standard otpauth:// URL standard for authenticator apps
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secretKey
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Scan the QR code URL or manually enter the secret key. Please store your recovery codes in a secure location.',
            'secret_key' => $secretKey,
            'qr_code_url' => $qrCodeUrl,
            // Return the RAW unencrypted codes ONLY this one time so the user can save them
            'recovery_codes' => $recoveryCodes
        ]);
    }

    /**
     * Step 2: Confirm 2FA activation by passing the first valid 6-digit code.
     */
    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $user = $request->user();

        // Verify code mathematically against their secret key
        $isValid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$isValid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid code. Activation failed.'
            ], 422);
        }

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Google Authenticator 2FA is now fully active.'
        ]);
    }

    /**
     * Step 3: Handle the login challenge verification.
     * Public route used immediately following the primary login attempt.
     */
    public function recoverTwoFactor(Request $request): JsonResponse
    {
        // 1. Validate the specific recovery code format
        $validated = $request->validate([
            'two_factor_token' => ['required', 'string'],
            'recovery_code' => ['required', 'string'],
        ]);

        // 2. Retrieve the temporary 2FA session ticket
        $session = Cache::get("2fa_login:{$validated['two_factor_token']}");

        if (!$session) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your authentication session has timed out.'
            ], 422);
        }

        $user = User::find($session['user_id']);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User account not found.'
            ], 404);
        }

        // 3. Decrypt and retrieve the user's stored recovery codes
        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?? [];

        // 4. Check if the submitted code exists in the array
        $codeIndex = array_search($validated['recovery_code'], $recoveryCodes);

        if ($codeIndex === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'The recovery code is incorrect or has already been used.'
            ], 422);
        }

        // 5. INVALIDATE THE RECOVERY CODE (Single-use mechanism)
        // Remove the specific code from the array so it can never be used again
        unset($recoveryCodes[$codeIndex]);

        // Re-index the array and re-encrypt it back to the database
        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes)))
        ])->save();

        // 6. INVALIDATE THE TEMPORARY 2FA SESSION
        Cache::forget("2fa_login:{$validated['two_factor_token']}");

        // Clear existing tokens for this specific device to prevent Sanctum table bloat
        $user->tokens()->where('name', $session['device_name'])->delete();

        // 7. Issue the official Sanctum passport
        $tokenAbility = "role:{$user->system_role->value}";
        $token = $user->createToken($session['device_name'], [$tokenAbility])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => $user,
            // Send back the remaining count so the frontend can warn the user if they are running out
            'recovery_codes_remaining' => count($recoveryCodes)
        ]);
    }

    /**
     * Deactivate 2FA protection.
     */
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ])->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Two-factor authentication has been completely turned off.'
        ]);
    }
}
