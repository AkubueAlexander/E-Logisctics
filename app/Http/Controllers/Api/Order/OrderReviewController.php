<?php

namespace App\Http\Controllers\Api\Order;

use App\Actions\Customer\SubmitMultiVendorReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SubmitReviewRequest;
use App\Models\Order;
use Exception;
use Illuminate\Http\JsonResponse;

class OrderReviewController extends Controller
{
    /**
     * Submit feedback matrices regarding a delivered order pipeline execution.
     */
    public function __invoke(SubmitReviewRequest $request, Order $order, SubmitMultiVendorReview $action): JsonResponse
    {
        try {
            $action->execute($order, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Thank you! Your reviews have been successfully recorded.'
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Unable to submit review data.',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
