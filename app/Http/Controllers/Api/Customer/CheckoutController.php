<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CheckoutRequest;
use App\Actions\Customer\PlaceMultiVendorOrder;
use App\Services\FlutterwaveService;
use Illuminate\Http\JsonResponse;
use Exception;

class CheckoutController extends Controller
{
    /**
     * Submit user payload variables to process checkout orchestration handlers.
     */
    // Inside CheckoutController.php (Injection in the method)
    public function __invoke(CheckoutRequest $request, PlaceMultiVendorOrder $action, FlutterwaveService $flutterwave): JsonResponse
    {
        try {
            $order = $action->execute($request->user(), $request->validated());

            // Ask the service for the link
            $paymentUrl = $flutterwave->generatePaymentLink($order, $request->user());

            return response()->json([
                'message' => 'Order created successfully. Redirect to payment link.',
                'order_id' => $order->id,
                'payment_url' => $paymentUrl,
                'data' => $order->load('subOrders.items')
            ], 201);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
