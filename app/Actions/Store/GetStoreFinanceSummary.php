<?php

namespace App\Actions\Store;

use App\Models\Store;
use App\Models\Ledger;
use App\DataTransferObjects\StoreFinanceSummaryDTO;
use Illuminate\Support\Facades\DB;

class GetStoreFinanceSummary
{
    /**
     * Compute the current financial position of a merchant store.
     */
    public function execute(Store $store): StoreFinanceSummaryDTO
    {
        // Aggregate totals grouped by status using raw sum expressions for raw speed
        $totals = Ledger::query()
            ->where('store_id', $store->id)
            ->select('status', DB::raw('SUM(amount_minor_unit) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return new StoreFinanceSummaryDTO(
            storeId: $store->id,
            pendingEscrowMinorUnit: (int) ($totals['pending'] ?? 0),
            withdrawableMinorUnit: (int) ($totals['cleared'] ?? 0),
            currencyCode: $store->currency_code ?? 'NGN'
        );
    }
}
