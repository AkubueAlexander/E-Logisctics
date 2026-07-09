<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\SubOrder;
use App\Services\StoreOrderManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SubOrderActionController extends Controller
{
    public function __construct(
        private StoreOrderManagementService $orderService
    ) {}

    public function accept(Request $request, SubOrder $subOrder): JsonResponse
    {
        // Ensure the authenticated user has access to this store's sub-order
        Gate::authorize('update', $subOrder->store);

        try {
            $updatedSubOrder = $this->orderService->acceptSubOrder(
                $subOrder,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Sub-order accepted successfully.',
                'data' => $updatedSubOrder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function cancel(Request $request, SubOrder $subOrder): JsonResponse
    {
        Gate::authorize('update', $subOrder->store);

        $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        try {
            $updatedSubOrder = $this->orderService->cancelSubOrder(
                $subOrder,
                $request->user()->id,
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Sub-order cancelled successfully.',
                'data' => $updatedSubOrder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
