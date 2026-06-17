<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\ToggleStoreStatusRequest;
use App\Actions\Store\ToggleStoreStatus;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StoreStatusController extends Controller
{
    /**
     * Toggle the active merchant operating state.
     */
    public function __invoke(ToggleStoreStatusRequest $request, Store $store, ToggleStoreStatus $action): JsonResponse
    {
        // Protect the endpoint: Ensure only authorized staff/owners can pull the plug
        Gate::authorize('update', $store);

        $updatedStore = $action->execute($store, $request->input('is_active'));

        return response()->json([
            'message' => "Store operational status changed to open",
            'data' => [
                'store_id' => $updatedStore->id,
                'is_active'   => $updatedStore->is_active,
            ]
        ], 200);
    }
}
