<?php

namespace App\Http\Controllers\Api\Financial;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WalletSummaryController extends Controller
{
    /**
     * Retrieve the real-time calculated balance and granular audit ledger logs.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if the user is querying as a merchant store or as an individual driver
        $storeId = $request->query('store_id');

        $query = Ledger::query();

        if ($storeId) {
            // Ensure the actor actually owns this store
            if ($user->stores()->where('id', $storeId)->doesntExist()) {
                return response()->json(['message' => 'Unauthorized store context.'], 403);
            }
            $query->where('store_id', $storeId);
        } else {
            $query->where('user_id', $user->id);
        }

        // Compute the precise financial metrics using row summaries
        $metrics = (clone $query)->selectRaw("
            SUM(CASE WHEN transaction_type IN ('store_payout', 'driver_payout') AND status = 'completed' THEN amount_minor_unit ELSE 0 END) as total_credits,
            SUM(CASE WHEN transaction_type IN ('store_withdrawal', 'driver_withdrawal') AND status IN ('pending', 'completed') THEN amount_minor_unit ELSE 0 END) as total_debits
        ")->first();

        $clearBalanceMinor = ($metrics->total_credits ?? 0) - ($metrics->total_debits ?? 0);

        // Get paginated recent logs
        $history = $query->latest()
            ->paginate(15)
            ->through(fn ($item) => [
                'id'                 => $item->id,
                'transaction_type'   => $item->transaction_type,
                'amount_main_unit'   => $item->amount_minor_unit / 100,
                'currency'           => $item->currency_code,
                'status'             => $item->status,
                'notes'              => $item->notes,
                'created_at'         => $item->created_at->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'withdrawable_balance' => $clearBalanceMinor / 100,
                'currency'             => 'NGN',
                'ledger_history'       => $history
            ]
        ], 200);
    }
}
