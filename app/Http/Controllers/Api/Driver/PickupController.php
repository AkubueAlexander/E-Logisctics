<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\SubOrder;
use App\Actions\Driver\MarkSubOrderPickedUp;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PickupController extends Controller
{
    /**
     * Handle physical item handover from vendor to courier.
     */
    public function __invoke(Request $request, SubOrder $subOrder, MarkSubOrderPickedUp $action): JsonResponse
    {
        try {
            $action->execute($request->user(), $subOrder);

            return response()->json([
                'message' => 'Items picked up successfully. Proceed to delivery.',
                'status'  => 'in_transit'
            ], 200);

        } catch (\Exception $e) {
            $statusCode = current(array_filter([$e->getCode(), 422]));
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                $statusCode = $e->getStatusCode();
            }

            return response()->json([
                'message' => 'Pickup confirmation failed.',
                'error'   => $e->getMessage()
            ], $statusCode);
        }
    }
}
