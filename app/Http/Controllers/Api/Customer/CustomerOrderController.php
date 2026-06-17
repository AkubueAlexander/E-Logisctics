<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerOrderController extends Controller
{
    /**
     * List order history for the authenticated customer.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with(['subOrders.store', 'subOrders.items'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    /**
     * Show detailed progress and driver location for an active order.
     */
    public function show(Request $request, Order $order): JsonResponse|OrderResource
    {
        // Security Guard: Ensure customers can only track their own purchases
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to order tracking.'], 403);
        }

        // Eager load everything needed for the resource transformation to prevent N+1 query leaks
        $order->load(['driver.driverProfile', 'subOrders.store', 'subOrders.items']);

        return new OrderResource($order);
    }
}
