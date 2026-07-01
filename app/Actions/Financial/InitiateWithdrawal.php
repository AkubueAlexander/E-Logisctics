<?php

namespace App\Actions\Financial;

use App\Models\Ledger;
use App\Models\User;
use App\Models\BankAccount;
use App\Jobs\ProcessFlutterwaveTransfer;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InitiateWithdrawal
{
    public function execute(User $actor, array $payload): Ledger
    {
        return DB::transaction(function () use ($actor, $payload) {

            $amountRequested = (int) $payload['amount_minor_unit'];
            $storeId = !empty($payload['store_id']) ? (int) $payload['store_id'] : null;
            $userId = null;

            // 1. Establish Context and Operational Identity Ownership Boundaries
            if ($storeId) {
                $hasAccess = DB::table('stores')
                    ->where('id', $storeId)
                    ->where('owner_id', $actor->id)
                    ->exists();

                if (!$hasAccess) {
                    throw new AccessDeniedHttpException('Unauthorized store administrative context.');
                }

                $bankExists = BankAccount::where('store_id', $storeId)->where('is_primary', true)->exists();
                $transactionType = 'store_withdrawal';
            } else {
                $userId = $actor->id;
                $bankExists = BankAccount::where('user_id', $userId)->where('is_primary', true)->exists();
                $transactionType = 'driver_withdrawal';
            }

            // 2. Validate Target Bank Configuration Status
            if (!$bankExists) {
                throw new UnprocessableEntityHttpException('You must onboard and verify a primary bank account before requesting withdrawal.');
            }

            // 3. Dynamic Multi-Tenant Wallet Balance Balance Calculus
            // Balance = (Sum of all incoming credits) - (Sum of all withdrawals whether pending or complete)
            $query = Ledger::query();
            if ($storeId) {
                $query->where('store_id', $storeId);
            } else {
                $query->where('user_id', $userId);
            }

            $financialMetrics = $query->selectRaw("
                SUM(CASE WHEN transaction_type IN ('store_payout', 'driver_payout') AND status = 'completed' THEN amount_minor_unit ELSE 0 END) as total_credits,
                SUM(CASE WHEN transaction_type IN ('store_withdrawal', 'driver_withdrawal') AND status IN ('pending', 'completed') THEN amount_minor_unit ELSE 0 END) as total_debits
            ")->first();

            $clearBalance = ($financialMetrics->total_credits ?? 0) - ($financialMetrics->total_debits ?? 0);

            if ($amountRequested > $clearBalance) {
                throw new UnprocessableEntityHttpException("Insufficient funds. Your current maximum withdrawable balance is ₦" . number_format($clearBalance / 100, 2));
            }

            // 4. Create the Ledger Freeze Record
            $ledger = Ledger::create([
                'order_id'          => null,
                'sub_order_id'      => null,
                'transaction_type'  => $transactionType,
                'store_id'          => $storeId,
                'user_id'           => $userId,
                'amount_minor_unit' => $amountRequested,
                'currency_code'     => 'NGN',
                'status'            => 'pending', // Keeps funds frozen and locked away from double-spending loops
            ]);

            // 5. Defer API Execution Out to Queue Worker Engine
            ProcessFlutterwaveTransfer::dispatch($ledger->id);

            return $ledger;
        });
    }
}
