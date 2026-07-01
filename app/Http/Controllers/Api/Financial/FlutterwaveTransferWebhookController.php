<?php

namespace App\Http\Controllers\Api\Financial;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlutterwaveTransferWebhookController extends Controller
{
    /**
     * Process asynchronous payout settlement state metrics from Flutterwave Transfers.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // 1. Cryptographic Signature Validation Guard
        $signature = $request->header('verif-hash');
        $localHash = config('services.flutterwave.webhook_hash');

        if (empty($signature) || $signature !== $localHash) {
            Log::warning('Unauthorized Flutterwave Payout Webhook Attempt Detained', [
                'ip' => $request->ip(),
                'provided_hash' => $signature
            ]);
            return response()->json(['message' => 'Signature verification failed.'], 401);
        }

        $event = $request->input('event'); // "transfer.completed" or "transfer.failed"
        $data = $request->input('data');

        if (empty($data) || empty($data['reference'])) {
            return response()->json(['message' => 'Malformed packet structure.'], 400);
        }

        // Parse our internal Ledger ID from the unique tracking reference ("wd_ref_{id}")
        preg_match('/wd_ref_(\d+)/', $data['reference'], $matches);
        $ledgerId = isset($matches[1]) ? (int)$matches[1] : null;

        if (!$ledgerId) {
            return response()->json(['message' => 'Reference syntax unrecognized.'], 200);
        }

        try {
            // 2. Concurrency Processing Enclosure via Row Lock
            DB::transaction(function () use ($ledgerId, $event, $data) {

                $ledger = Ledger::where('id', $ledgerId)->lockForUpdate()->first();

                if (!$ledger) {
                    Log::error("Payout Webhook references non-existent Ledger index", ['ledger_id' => $ledgerId]);
                    return;
                }

                // IDEMPOTENCY GUARD: If already processed out of the pending state, ignore duplicate payload drops
                if ($ledger->status !== 'pending') {
                    Log::info("Idempotency match triggered. Payout Webhook skipped.", [
                        'ledger_id' => $ledgerId,
                        'current_status' => $ledger->status
                    ]);
                    return;
                }

                // 3. State Machine Processing
                if ($event === 'transfer.completed') {

                    $ledger->update([
                        'status' => 'completed',
                        'notes'  => 'Disbursement finalized by Flutterwave gateway.'
                    ]);

                    Log::info("Withdrawal verified and settled successfully.", ['ledger_id' => $ledgerId]);

                } elseif ($event === 'transfer.failed') {

                    // Technical Note: Changing this status directly to 'failed' automatically
                    // unfreezes the assets and restores them to the user's withdrawable balance instantly.
                    $ledger->update([
                        'status' => 'failed',
                        'notes'  => 'Gateway rejection reason: ' . ($data['complete_message'] ?? 'Unknown network failure.')
                    ]);

                    Log::warning("Withdrawal pipeline execution failed. Wallet balance automatically reconstituted.", [
                        'ledger_id' => $ledgerId,
                        'reason'    => $data['complete_message'] ?? 'Unknown'
                    ]);
                }
            });

            // Always tell Flutterwave you received the packet successfully so they stop retrying
            return response()->json(['status' => 'reconciliation_achieved'], 200);

        } catch (\Exception $e) {
            Log::critical("System Crash during Payout Webhook Reconciliation Pipeline Execution", [
                'ledger_id' => $ledgerId,
                'error'     => $e->getMessage()
            ]);

            return response()->json(['message' => 'Internal processing degradation.'], 500);
        }
    }
}
