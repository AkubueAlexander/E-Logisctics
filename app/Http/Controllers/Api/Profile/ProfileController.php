<?php
namespace App\Http\Controllers\Api\Profile;

use App\Actions\User\UpdateUserProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, UpdateUserProfile $action): JsonResponse
    {
        $user = $action->execute(
            $request->user(),
            $request->validated(),
            $request->file('photo')
        );

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $user
        ]);
    }
}
