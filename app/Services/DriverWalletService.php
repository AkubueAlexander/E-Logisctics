<?php

namespace App\Services;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DriverWalletService
{
    /**
     * Get aggregated financial metrics for a driver.
     */
    public function getMetrics(User $driver): array
    {
        // Calculate total earnings from completed jobs
        $totalEarned = Ledger::where('user_id', $driver->id)
            ->where('transaction_type', 'driver_payout')
            ->where('status', 'completed')
            ->sum('amount_minor_unit');

        // Calculate total successful withdrawals processed out of the system
        $totalWithdrawn = Ledger::where('user_id', $driver->id)
            ->where('transaction_type', 'driver_withdrawal')
            ->where('status', 'completed')
            ->sum('amount_minor_unit');

        // Net clear balance available for cash-out
        $withdrawableBalance = $totalEarned - $totalWithdrawn;

        // Retrieve last 10 transaction lines for the feed
        $history = Ledger::where('user_id', $driver->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'id'                 => $item->id,
                'type'               => $item->transaction_type,
                'amount_minor_unit'  => $item->amount_minor_unit,
                'status'             => $item->status,
                'created_at'         => $item->created_at->toIso8601String(),
            ]);

        return [
            'currency'                  => 'NGN',
            'withdrawable_balance_minor'=> $withdrawableBalance,
            'total_earned_minor'        => $totalEarned,
            'history'                   => $history,
        ];
    }
}
