<?php
namespace App\Http\Controllers\Api\Store;

use App\Actions\Store\SaveStoreProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\SaveStoreProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use App\Models\Store;

class StoreController extends Controller
{
    public function store(SaveStoreProfileRequest $request, SaveStoreProfile $action): JsonResponse
    {
        // Enforces that the user must already be a manager somewhere in the system
        Gate::authorize('create', Store::class);

        $store = $action->execute(
            $request->user(),
            $request->validated(),
            null, // Explicitly null because we are creating a new store
            $request->file('logo')
        );

        return response()->json([
            'message' => 'Store profile created successfully.',
            'data' => $store
        ], 201);
    }

    /**
     * PUT /api/stores/{store}
     * Update a specific store profile.
     */
    public function update(SaveStoreProfileRequest $request, Store $store, SaveStoreProfile $action): JsonResponse
    {
        // Enforces that the user must be a manager of THIS specific store instance
        Gate::authorize('update', $store);

        $store = $action->execute(
            $request->user(),
            $request->validated(),
            $store,
            $request->file('logo')
        );

        return response()->json([
            'message' => 'Store profile updated successfully.',
            'data' => $store
        ], 200);
    }
}
