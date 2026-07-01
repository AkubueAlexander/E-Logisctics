<?php

namespace App\Listeners\Finance;

use App\Events\SubOrderCancelled;
use App\Services\LedgerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ReconcilePartialCancellation implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'finance';
    public bool $afterCommit = true; // Wait until MySQL completes transaction safety loops
    public int $tries = 5; // Higher retry allocation for critical financial items
    public array $backoff = [5, 10, 20];

    public function __construct(
        private LedgerService $ledger
    ) {}

    public function handle(SubOrderCancelled $event): void
    {
        $subOrder = $event->subOrder;
        $order = $subOrder->order;

        if (!$order) {
            Log::error("Ledger Reconciliation Orphaned: SubOrder #{$subOrder->id} has no valid parent relation.");
            return;
        }

        // 1. Credit the customer's internal wallet for the exact amount of the cancelled leg
        $this->ledger->creditWallet(
            userId: $order->user_id,
            amountMinorUnit: $subOrder->total_minor_unit,
            description: "Refund for item unavailability from Store: " . ($subOrder->store->name ?? 'Merchant')
        );

        // 2. Adjust corporate escrow tracking collections to ensure the merchant isn't paid out
        $this->ledger->voidVendorEscrow(
            storeId: $subOrder->store_id,
            subOrderId: $subOrder->id
        );
    }

    public function failed(SubOrderCancelled $event, \Throwable $exception): void
    {
        Log::critical("CRITICAL LEDGER MISMATCH: Failed to reconcile refund for SubOrder #{$event->subOrder->id}", [
            'exception' => $exception->getMessage(),
            'order_id' => $event->subOrder->order_id
        ]);
    }
}
