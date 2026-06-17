<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\UpdateSubOrderStatusRequest;
use App\Actions\Order\UpdateSubOrderState;
use App\Models\Store;
use App\Models\SubOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MerchantOrderController extends Controller
{
    /**
     * List incoming or active sub-orders for a specific merchant.
     */
    public function index(Store $store): JsonResponse
    {
        Gate::authorize('update', $store);

        $subOrders = $store->subOrders()
            ->with(['items', 'order:id,delivery_address,latitude,longitude,status,created_at'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($subOrders, 200);
    }

    /**
     * Mutate the active processing state of a targeted sub-order.
     */
    public function update(
        UpdateSubOrderStatusRequest $request,
        Store $store,
        SubOrder $subOrder,
        UpdateSubOrderState $action
    ): JsonResponse {
        Gate::authorize('update', $store);

        // Security Guard: Defend against cross-tenant endpoint execution
        if ($subOrder->store_id !== $store->id) {
            return response()->json(['message' => 'Sub-order resource mismatch.'], 403);
        }

        $updatedSubOrder = $action->execute($subOrder, $request->validated());

        return response()->json([
            'message' => 'Sub-order status updated successfully.',
            'sub_order_status' => $updatedSubOrder->status,
            'parent_order_status' => $updatedSubOrder->order->status
        ], 200);
    }
}
