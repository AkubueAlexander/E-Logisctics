<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FlutterwaveService; // <-- Inject your service
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class InitializePaymentController extends Controller
{
    public function initialize(Request $request, $order_id, FlutterwaveService $flutterwave): JsonResponse
    {
        $order = Order::where('id', $order_id)
            ->where('customer_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'This order has already been paid for.'], 400);
        }

        try {

            $paymentUrl = $flutterwave->generatePaymentLink($order, $request->user());

            return response()->json([
                'message'      => 'Payment link regenerated successfully.',
                'order_id'     => $order->id,
                'payment_link' => $paymentUrl
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Unable to initialize payment gateway.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
