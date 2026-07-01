<?php

namespace App\Http\Controllers\Api\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\OnboardBankRequest;
use App\Actions\Financial\OnboardBankAccount;
use Illuminate\Http\JsonResponse;

class BankOnboardingController extends Controller
{
    /**
     * Verify banking details against NIBSS systems and attach to the profile entity.
     */
    public function __invoke(OnboardBankRequest $request, OnboardBankAccount $action): JsonResponse
    {
        try {
            $bankAccount = $action->execute($request->user(), $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Bank account verified and linked successfully.',
                'data'    => [
                    'id'             => $bankAccount->id,
                    'bank_name'      => $bankAccount->bank_name,
                    'account_number' => $bankAccount->account_number,
                    'account_name'   => $bankAccount->account_name,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Banking validation failed.',
                'error'   => $e->getMessage()
            ], 422);
        }
    }
}
