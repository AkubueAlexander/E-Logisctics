<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\ResendOtpAction;
use App\Http\Requests\Api\ResendOtpRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ResendOtpController extends Controller
{
    public function __invoke(ResendOtpRequest $request, ResendOtpAction $action): JsonResponse
    {
        // Execute the action using the validated email
        $action($request->validated('email'));

        return response()->json([
            'status' => 'success',
            'message' => 'A new verification code has been sent to your device.'
        ], 200);
    }
}
