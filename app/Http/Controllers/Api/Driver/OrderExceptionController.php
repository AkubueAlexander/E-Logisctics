<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Actions\Driver\HandleCustomerNoShow;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderExceptionController extends Controller
{
    /**
     * Trigger a customer no-show terminal cancellation.
     */
    public function triggerNoShow(Request $request, Order $order, HandleCustomerNoShow $action): JsonResponse
    {
        try {
            $updatedOrder = $action->execute($order, $request->user());

            return response()->json([
                'message' => 'Order flagged as customer no-show. Payouts cleared and route closed.',
                'status' => $updatedOrder->status
            ], 200);

        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
