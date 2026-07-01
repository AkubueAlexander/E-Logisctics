<?php

namespace App\Actions\Financial;

use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPayoutSettlement
{
    public function execute(string $event, array $data): void
    {
        preg_match('/wd_ref_(\d+)/', $data['reference'] ?? '', $matches);
        $ledgerId = isset($matches[1]) ? (int)$matches[1] : null;

        if (!$ledgerId) {
            return;
        }

        DB::transaction(function () use ($ledgerId, $event, $data) {
            $ledger = Ledger::where('id', $ledgerId)->lockForUpdate()->first();

            if (!$ledger || $ledger->status !== 'pending') {
                return; // Idempotency or Non-existent Guard
            }

            if ($event === 'transfer.completed') {
                $ledger->update([
                    'status' => 'completed',
                    'notes'  => 'Disbursement finalized by Flutterwave gateway.'
                ]);
                Log::info("Withdrawal settled successfully.", ['ledger_id' => $ledgerId]);
            } elseif ($event === 'transfer.failed') {
                $ledger->update([
                    'status' => 'failed',
                    'notes'  => 'Gateway rejection reason: ' . ($data['complete_message'] ?? 'Unknown network failure.')
                ]);
                Log::warning("Withdrawal pipeline execution failed.", ['ledger_id' => $ledgerId]);
            }
        });
    }
}
