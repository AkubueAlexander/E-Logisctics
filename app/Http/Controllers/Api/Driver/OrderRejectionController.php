<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\MissionPing;
use App\Actions\Driver\RejectOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderRejectionController extends Controller
{
    /**
     * Handle the incoming request when a driver declines a mission offer.
     */
    public function __invoke(Request $request, MissionPing $ping, RejectOrder $action): JsonResponse
    {
        $driver = $request->user();

        // Multi-tenant Security Check: Ensure this driver actually owns this ping assignment
        if ($ping->driver_id !== $driver->id) {
            return response()->json([
                'message' => 'Unauthorized: This mission offer was not dispatched to you.'
            ], 403);
        }

        try {
            // Defer execution entirely to the Action domain class
            $action->execute($ping);

            return response()->json([
                'message' => 'Mission offer declined successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Could not process the rejection request.',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
