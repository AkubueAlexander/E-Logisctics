<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Actions\Store\GetStoreFinanceSummary;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StoreFinanceController extends Controller
{
    /**
     * Fetch a high-level financial health readout for the merchant.
     */
    public function __invoke(Store $store, GetStoreFinanceSummary $action): JsonResponse
    {
        // Enforce Multi-tenant isolation: Only verified store managers or owners can access financial data
        Gate::authorize('viewEarnings', $store);

        $summary = $action->execute($store);

        return response()->json([
            'message' => 'Financial summary retrieved successfully.',
            'data' => $summary->toArray()
        ], 200);
    }
}
