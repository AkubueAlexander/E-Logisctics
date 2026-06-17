<?php
namespace App\Http\Controllers\Api;

use App\Actions\Auth\RegisterUserAction;
use App\DataTransferObjects\RegisterUserDTO;
use App\Http\Requests\Api\RegisterUserRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(RegisterUserRequest $request, RegisterUserAction $action): JsonResponse
    {
        // Hydrate using your precise factory method
        $dto = RegisterUserDTO::fromRequest($request);

        $action($dto);


        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful. Please verify your account using the OTP sent to your device.'
        ], 201);
    }
}
