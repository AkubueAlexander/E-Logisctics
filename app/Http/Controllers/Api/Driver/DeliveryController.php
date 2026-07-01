<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\SubOrder;
use App\Http\Requests\Driver\DeliveryVerificationRequest;
use App\Actions\Driver\CompleteDelivery;
use Illuminate\Http\JsonResponse;

class DeliveryController extends Controller
{
    /**
     * Handle final handover to the customer and unlock financial ledgers.
     */
    public function __invoke(
        DeliveryVerificationRequest $request,
        SubOrder $subOrder,
        CompleteDelivery $action
    ): JsonResponse {
        try {
            $action->execute(
                $request->user(),
                $subOrder,
                $request->validated('latitude'),
                $request->validated('longitude'),
                $request->validated('delivery_pin')
            );

            return response()->json([
                'message' => 'Order delivered successfully. Escrow funds released.',
                'status'  => 'delivered'
            ], 200);

        } catch (\Exception $e) {
            $statusCode = current(array_filter([$e->getCode(), 422]));
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                $statusCode = $e->getStatusCode();
            }

            return response()->json([
                'message' => 'Delivery confirmation failed.',
                'error'   => $e->getMessage()
            ], $statusCode);
        }
    }
}
