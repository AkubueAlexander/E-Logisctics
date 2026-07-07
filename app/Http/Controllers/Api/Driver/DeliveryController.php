<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\SubOrder;
use App\Http\Requests\Driver\DeliveryVerificationRequest;
use App\Actions\Driver\CompleteDelivery;
use Illuminate\Http\JsonResponse;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeliveryController extends Controller
{
    /**
     * Handle final handover to the customer, verify OTP, and trigger financial settlement.
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
                (float) $request->validated('latitude'),
                (float) $request->validated('longitude'),
                (string) $request->validated('otp')
            );

            return response()->json([
                'message' => 'Delivery verified successfully. Settlement initiated.',
                'status'  => 'delivered'
            ], 200);

        } catch (Exception $e) {
            $statusCode = 422;

            if ($e instanceof HttpException) {
                $statusCode = $e->getStatusCode();
            } elseif ($e->getCode() >= 400 && $e->getCode() < 600) {
                $statusCode = $e->getCode();
            }

            return response()->json([
                'message' => 'Delivery confirmation failed.',
                'error'   => $e->getMessage()
            ], $statusCode);
        }
    }
}
