<?php
namespace App\Http\Controllers\Api\Store;

use App\Actions\Store\InviteRepresentative;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\InviteRepresentativeRequest;
use Illuminate\Http\JsonResponse;

class StoreStaffController extends Controller
{
    public function invite(InviteRepresentativeRequest $request, InviteRepresentative $action): JsonResponse
    {
        // 1. Identify which restaurant the manager is currently managing
        // Assuming a manager only manages one restaurant for now.
        $store = $request->user()->stores()->wherePivot('role', 'manager')->firstOrFail();

        // 2. Execute invitation
        $user = $action->execute($store, $request->validated());

        return response()->json([
            'message' => 'Representative invited successfully. An email has been sent to set their password.',
            'data' => $user
        ], 201);
    }
}
