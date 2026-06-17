<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\PayoutRequest;
use App\Actions\Driver\ProcessPayoutRequest;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    /**
     * Get wallet statement and account balances.
     */
    public function index(Request $request): JsonResponse
    {
        $driverId = $request->user()->id;

        // Fetch aggregates
        $credits = Ledger::where('user_id', $driverId)->where('status', 'cleared')->where('transaction_type', 'driver_payout')->sum('amount_minor_unit');
        $debits = Ledger::where('user_id', $driverId)->where('transaction_type', 'driver_withdrawal')->sum('amount_minor_unit');

        $availableBalance = $credits - $debits;

        // Fetch recent ledger history entries
        $history = Ledger::where('user_id', $driverId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'balances' => [
                'withdrawable_minor_unit' => (int) $availableBalance,
                'withdrawable_formatted' => number_format($availableBalance / 100, 2),
                'currency' => 'NGN'
            ],
            'statement' => $history
        ], 200);
    }

    /**
     * Post a withdrawal request payload.
     */
    public function withdraw(PayoutRequest $request, ProcessPayoutRequest $action): JsonResponse
    {
        $withdrawalRow = $action->execute($request->user(), $request->validated());

        return response()->json([
            'message' => 'Payout request has been registered and is pending processing.',
            'payout_reference_id' => $withdrawalRow->id,
            'amount_minor_unit' => $withdrawalRow->amount_minor_unit
        ], 201);
    }
}
