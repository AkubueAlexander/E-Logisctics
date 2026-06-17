<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginUserAction;
use App\DataTransferObjects\LoginDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginController
{
    /**
     * Handle the primary login request.
     */
    public function __invoke(Request $request, LoginUserAction $action): JsonResponse
    {
        // 1. Validate the incoming HTTP request
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string']
        ]);

        // 2. Map validation data to the DTO.

        $dto = new LoginDTO(
            $validated['email'],
            $validated['password'],
            $validated['device_name'] // Use standard snake_case everywhere
        );

        // 3. Delegate business logic to the Action.
        // On bad credentials, this will throw a standard ValidationException (auto-returning 422).
        $result = $action->execute($dto);

        // 4. Route the appropriate HTTP response based on the domain outcome.
        return match ($result['status']) {
            // Case A: Account requires verification
            'unverified' => response()->json([
                'status' => 'unverified',
                'message' => 'Your account is not verified yet.'
            ], 403),

            // Case B: Account requires a 2FA challenge
            'requires_2fa' => response()->json([
                'status' => 'requires_2fa',
                'two_factor_token' => $result['two_factor_token'],
                'message' => 'Please enter the 6-digit code from your Google Authenticator app.'
            ], 200),

            // Case C: Standard successful login
            'success' => response()->json([
                'status' => 'success',
                'token' => $result['token'],
                'user' => $result['user']
            ], 200),
        };
    }
}
