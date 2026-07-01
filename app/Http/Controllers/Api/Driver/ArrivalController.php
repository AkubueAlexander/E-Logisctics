<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\SubOrder;
use App\Http\Requests\Driver\ArrivalVerificationRequest;
use App\Actions\Driver\MarkDriverArrived;
use Illuminate\Http\JsonResponse;

class ArrivalController extends Controller
{
    /**
     * Handle the driver's request to register their arrival at the vendor location.
     */
    public function __invoke(
        ArrivalVerificationRequest $request,
        SubOrder $subOrder,
        MarkDriverArrived $action
    ): JsonResponse {
        try {
            $action->execute(
                $request->user(),
                $subOrder,
                $request->validated('latitude'),
                $request->validated('longitude')
            );

            return response()->json([
                'message' => 'Arrival confirmed successfully.',
                'status'  => 'driver_arrived'
            ], 200);

        } catch (\Exception $e) {
            // Handle expected domain errors safely
            $statusCode = current(array_filter([$e->getCode(), 422])); // Default to 422 if code is 0
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                $statusCode = $e->getStatusCode();
            }

            return response()->json([
                'message' => 'Proximity check failed.',
                'error'   => $e->getMessage()
            ], $statusCode);
        }
    }
}
