<?php
namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\UpdateDriverProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DriverController extends Controller
{
    public function updateProfile(UpdateDriverProfileRequest $request): JsonResponse
    {
        // Use updateOrCreate in case the profile row wasn't generated during initial auth
        $profile = $request->user()->driverProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->only(['vehicle_type', 'license_plate'])
        );

        return response()->json([
            'message' => 'Driver profile updated',
            'data' => $profile
        ]);
    }

    public function toggleAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:offline,available,busy']
        ]);

        $profile = $request->user()->driverProfile;

        if (! $profile || $profile->verification_status !== 'verified') {
            return response()->json([
                'message' => 'Cannot change status. Driver profile is incomplete or unverified.'
            ], 403);
        }

        $profile->update(['availability_status' => $validated['status']]);

        return response()->json([
            'message' => "Status updated to {$validated['status']}",
            'data' => $profile
        ]);
    }
}
