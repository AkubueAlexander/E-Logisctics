<?php

namespace App\Http\Controllers\Api\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\InitiateWithdrawalRequest;
use App\Actions\Financial\InitiateWithdrawal;
use Illuminate\Http\JsonResponse;

class WithdrawalController extends Controller
{
    /**
     * Lock available wallet balances and queue outward payment pipeline routing.
     */
    public function __invoke(InitiateWithdrawalRequest $request, InitiateWithdrawal $action): JsonResponse
    {
        try {
            $ledger = $action->execute($request->user(), $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request received and processing.',
                'data'    => [
                    'reference'    => "wd_ref_{$ledger->id}",
                    'amount_minor' => $ledger->amount_minor_unit,
                    'status'       => $ledger->status,
                ]
            ], 202); // 202 Accepted indicates asynchronous execution queue handover

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal initiation rejected.',
                'error'   => $e->getMessage()
            ], 422);
        }
    }
}
