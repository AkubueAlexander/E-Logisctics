<?php

namespace App\Actions\Driver;

use App\Models\User;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;

class ProcessPayoutRequest
{
    /**
     * Deduct funds securely and queue a ledger record for bank disbursement.
     */
    public function execute(User $driver, array $data): Ledger
    {
        return DB::transaction(function () use ($driver, $data) {

            // Tech Lead Guard: Force a shared lock on user's financial records to prevent simultaneous API calls
            DB::table('ledgers')->where('user_id', $driver->id)->lockForUpdate()->get();

            // Book a debit withdrawal row into the ledger system
            return Ledger::create([
                'order_id' => null,
                'sub_order_id' => null,
                'transaction_type' => 'driver_withdrawal',
                'store_id' => null,
                'user_id' => $driver->id,
                'amount_minor_unit' => $data['amount_minor_unit'],
                'currency_code' => 'NGN',
                'status' => 'pending', // Switched to 'cleared' once the Flutterwave/Bank API payout webhook returns success
                'metadata' => json_encode([
                    'bank_code' => $data['bank_code'],
                    'account_number' => $data['account_number'],
                    'requested_at' => now()->toIso8601String()
                ])
            ]);
        });
    }
}
