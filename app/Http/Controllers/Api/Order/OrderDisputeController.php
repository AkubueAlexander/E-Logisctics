<?php

namespace App\Http\Controllers\Api\Order;

use App\Actions\Customer\FileSubOrderDispute;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\FileDisputeRequest;
use Exception;
use Illuminate\Http\JsonResponse;

class OrderDisputeController extends Controller
{
    /**
     * Log a grievance against a vendor and pause their financial disbursement.
     */
    public function __invoke(FileDisputeRequest $request, FileSubOrderDispute $action): JsonResponse
    {
        try {
            $disputedSubOrder = $action->execute($request->user(), $request->validated());

            return response()->json([
                'message' => 'Dispute opened successfully. Merchant payout has been frozen pending admin review.',
                'sub_order_id' => $disputedSubOrder->id,
                'sub_order_status' => $disputedSubOrder->status
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Could not process dispute submission.',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
