<?php

namespace App\Services;

use App\Models\Ledger;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class LedgerService
{
    /**
     * Credits any valid owner's wallet (Customer, Store, or Driver) polymorphically
     * and records a balancing entry in the platform ledger register.
     *
     * @param Model $owner The Eloquent instance owning the wallet (User, Store, etc.)
     * @param int $amountMinorUnit Value tracked strictly as an integer (cents/kobo)
     * @param string $description History text log for statement records
     * @param string $reference Deterministic unique fingerprint to prevent double-processing
     * @param int|null $orderId Macro platform order reference
     * @param int|null $subOrderId Granular leg order reference
     * @throws Exception
     */
    public function creditWallet(
        Model $owner, 
        int $amountMinorUnit, 
        string $description, 
        string $reference,
        ?int $orderId = null,
        ?int $subOrderId = null
    ): void {
        if ($amountMinorUnit <= 0) {
            throw new Exception("Financial Core Error: Credit value must be positive. Attempted: {$amountMinorUnit}");
        }

        // 1. Dynamically extract polymorphic identification from the model instance
        $ownerId = $owner->getKey();
        $ownerType = $owner->getMorphClass(); // Returns your clean model class string

        // 2. Just-In-Time Provisioning Guard: Ensure a ledger row exists for this specific actor type
        $this->ensureWalletExists($ownerId, $ownerType);

        DB::transaction(function () use ($ownerId, $ownerType, $amountMinorUnit, $description, $reference, $orderId, $subOrderId) {
            
            // 3. Pessimistic Lock: Isolate the specific polymorphic wallet row
            $wallet = DB::table('wallets')
                ->where('owner_id', $ownerId)
                ->where('owner_type', $ownerType)
                ->lockForUpdate()
                ->first();

            // 4. Strict Idempotency Check: Verify system hasn't already absorbed this reference
            $alreadyProcessed = WalletTransaction::where('wallet_id', $wallet->id)
                ->where('reference', $reference)
                ->exists();

            if ($alreadyProcessed) {
                Log::warning("LedgerEngine: Request blocked. Reference ID: {$reference} already processed on Wallet #{$wallet->id}");
                return;
            }

            $oldBalance = $wallet->balance_minor_unit;
            $newBalance = $oldBalance + $amountMinorUnit;

            // 5. Update Liquid Balance Account
            DB::table('wallets')
                ->where('id', $wallet->id)
                ->update([
                    'balance_minor_unit' => $newBalance,
                    'updated_at'         => now()
                ]);

            // 6. Append User-Facing Statement Entry
            WalletTransaction::create([
                'wallet_id'         => $wallet->id,
                'type'              => 'credit',
                'amount_minor_unit' => $amountMinorUnit,
                'running_balance'   => $newBalance,
                'description'       => $description,
                'reference'         => $reference,
            ]);

            // 7. Platform Balancing Double-Entry: Write to the core ledgers table
            if ($orderId && $subOrderId) {
                // Determine transaction type based on the class type passing through
                $isStore = is_subclass_of($ownerType, Model::class) 
                    ? (new $ownerType)->getTable() === 'stores' 
                    : str_contains(strtolower($ownerType), 'store');

                $transactionType = $isStore ? 'vendor_payout' : 'customer_refund';

                Ledger::create([
                    'order_id'           => $orderId,
                    'sub_order_id'       => $subOrderId,
                    'transaction_type'   => $transactionType,
                    'store_id'           => $isStore ? $ownerId : null,
                    'user_id'            => !$isStore ? $ownerId : null,
                    'amount_minor_unit'  => $amountMinorUnit,
                    'currency_code'      => 'NGN',
                    'status'             => 'completed',
                ]);
            }

            Log::info("LedgerEngine: Account [{$ownerType} #{$ownerId}] credited. Balance shifted: {$oldBalance} -> {$newBalance} NGN.");
        });
    }

    /**
     * Voids a vendor's escrow payout record using your Eloquent model context.
     */
    public function voidVendorEscrow(int $storeId, int $subOrderId): void
    {
        DB::transaction(function () use ($storeId, $subOrderId) {
            
            // Acquire row-level write-lock on the specific merchant platform asset row
            $escrowRecord = Ledger::where('sub_order_id', $subOrderId)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            // Guard Clause: If the ledger transaction record is missing entirely, log and alert
            if (!$escrowRecord) {
                Log::error("LedgerEngine: Escrow adjustments aborted. Record missing for SubOrder #{$subOrderId}, Store #{$storeId}");
                return;
            }

            // Idempotency check using model property state
            if ($escrowRecord->status === 'voided') {
                Log::info("LedgerEngine: Escrow line for SubOrder #{$subOrderId} is already voided. Skipping transaction rewrite.");
                return;
            }

            // Enforce hard infrastructure validation rules
            if (in_array($escrowRecord->status, ['captured', 'paid'])) {
                Log::critical("SECURITY VIOLATION: System attempted to void an escrow line that has already been cleared or settled. SubOrder #{$subOrderId}");
                return;
            }

            // Execute update using your model instance wrapper safely
            $escrowRecord->update([
                'status'     => 'voided',
                'updated_at' => now(),
            ]);

            Log::info("LedgerEngine: Successfully terminated merchant escrow allocation. Ledger ID #{$escrowRecord->id} for SubOrder #{$subOrderId} set to VOIDED.");
        });
    }

    /**
     * Thread-Safe Polymorphic Just-In-Time Provisioner.
     */
    private function ensureWalletExists(int $ownerId, string $ownerType): void
    {
        DB::statement("
            INSERT INTO wallets (owner_id, owner_type, balance_minor_unit, currency, created_at, updated_at)
            VALUES (?, ?, 0, 'NGN', NOW(), NOW())
            ON CONFLICT (owner_type, owner_id) DO NOTHING
        ", [$ownerId, $ownerType]);
    }
}