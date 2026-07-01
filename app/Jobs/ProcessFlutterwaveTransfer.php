<?php

namespace App\Jobs;

use App\Models\Ledger;
use App\Services\Gateways\FlutterwaveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessFlutterwaveTransfer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Retry job up to 3 times if network drops out
    public $tries = 3;
    public $backoff = 60;

    public function __construct(protected int $ledgerId) {}

    public function handle(FlutterwaveService $flwService): void
    {
        $ledger = Ledger::find($this->ledgerId);

        // Guard against deleted records or logs already processed by a webhook race condition
        if (!$ledger || $ledger->status !== 'pending') {
            return;
        }

        // Determine who owns the payout destination profile
        $bankAccount = $ledger->store_id
            ? $ledger->store->bankAccounts()->where('is_primary', true)->first()
            : $ledger->user->bankAccounts()->where('is_primary', true)->first();

        if (!$bankAccount) {
            $this->failWithdrawal($ledger, 'No primary verified bank account attached to entity.');
            return;
        }

        try {
            $narration = $ledger->transaction_type === 'store_withdrawal'
                ? "Merchant Settlement #{$ledger->id}"
                : "Courier Earnings Egress #{$ledger->id}";

            // Call Flutterwave API
            $flwService->createTransfer(
                reference: "wd_ref_{$ledger->id}",
                amountMinor: $ledger->amount_minor_unit,
                bankCode: $bankAccount->bank_code,
                accountNumber: $bankAccount->account_number,
                narration: $narration
            );

            // Do NOT mark as completed here. We wait exclusively for the Webhook confirmation.
            Log::info("Transfer safely queued on Flutterwave.", ['ledger_id' => $ledger->id]);

        } catch (Exception $e) {
            Log::error("Withdrawal Job Processing Error", [
                'ledger_id' => $ledger->id,
                'error'     => $e->getMessage()
            ]);

            // If we have exhausted all attempts, mark the system ledger line explicitly as failed
            if ($this->attempts() >= $this->tries) {
                $this->failWithdrawal($ledger, 'Gateway initialization failure: ' . $e->getMessage());
            }

            throw $e; // Re-throw to trigger standard queue backoff rules
        }
    }

    protected function failWithdrawal(Ledger $ledger, string $reason): void
    {
        $ledger->update([
            'status' => 'failed',
            'notes'  => $reason
        ]);
    }
}
