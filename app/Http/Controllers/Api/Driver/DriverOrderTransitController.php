<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Actions\Driver\CompleteDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class DriverOrderTransitController extends Controller
{
    /**
     * Mark courier arrival context footprint at the pick-up hub coordinates.
     */
    public function arriveAtMerchant(Request $request, Order $order): JsonResponse
    {
        if ($order->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized driver assignment.'], 403);
        }

        if ($order->status !== 'driver_assigned') {
            return response()->json(['message' => 'Invalid order status transition path.'], 400);
        }

        $order->update(['status' => 'driver_arrived']);

        return response()->json([
            'message' => 'Arrival acknowledged. Awaiting food package compilation confirmation.',
            'status' => 'driver_arrived'
        ], 200);
    }

    /**
     * Transition the route payload package footprint to active transit state.
     */
    public function collectOrder(Request $request, Order $order): JsonResponse
    {
        if ($order->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized driver assignment.'], 403);
        }

        // Allow collection from either assigned or arrived checkpoints
        if (!in_array($order->status, ['driver_assigned', 'driver_arrived'])) {
            return response()->json(['message' => 'Cannot collect items for an unassigned or completed order.'], 400);
        }

        // Verify that merchants have marked their packages as ready_for_pickup
        $unreadyItemsCount = $order->subOrders()->where('status', '!=', 'ready_for_pickup')->count();
        if ($unreadyItemsCount > 0) {
            return response()->json(['message' => 'Cannot transition to transit. Some vendor baskets are still cooking.'], 400);
        }

        // Advance states
        $order->update(['status' => 'in_transit']);
        $order->subOrders()->update(['status' => 'in_transit']);

        return response()->json([
            'message' => 'Package picked up successfully. Navigation transit layer activated.',
            'status' => 'in_transit'
        ], 200);
    }

    /**
     * Complete the entire logistics flow pipeline and trigger financial disbursements.
     */
    public function completeDelivery(Request $request, Order $order, CompleteDelivery $action): JsonResponse
    {
        try {
            $completedOrder = $action->execute($order, $request->user());

            return response()->json([
                'message' => 'Delivery marked completed successfully. Escrow funds transferred to wallet layers.',
                'status' => $completedOrder->status
            ], 200);

        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
