<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\MissionPing;
use App\Actions\Driver\AcceptOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class OrderAcceptanceController extends Controller
{
    /**
     * Handle the incoming claim request for a specific dispatch ping.
     */
    public function __invoke(Request $request, MissionPing $ping, AcceptOrder $action): JsonResponse
    {
        try {
            // Forward the validated driver and ping models directly to the action baseline
            $action->execute($ping, $request->user());

            return response()->json([
                'message' => 'Mission claimed successfully.',
                'ping_id' => $ping->id,
                'mission_id' => $ping->delivery_mission_id
            ], 200);

        } catch (RuntimeException $e) {
            // Handles both concurrent claims and expired timeouts cleanly
            return response()->json([
                'message' => $e->getMessage()
            ], 409);
        }
    }
}
