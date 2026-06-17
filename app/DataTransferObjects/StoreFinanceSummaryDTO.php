<?php

namespace App\DataTransferObjects;

class StoreFinanceSummaryDTO
{
    public function __construct(
        public int $storeId,
        public int $pendingEscrowMinorUnit,
        public int $withdrawableMinorUnit,
        public string $currencyCode
    ) {}

    public function toArray(): array
    {
        return [
            'store_id' => $this->storeId,
            'pending_escrow' => [
                'minor_unit' => $this->pendingEscrowMinorUnit,
                'formatted' => number_format($this->pendingEscrowMinorUnit / 100, 2),
            ],
            'withdrawable' => [
                'minor_unit' => $this->withdrawableMinorUnit,
                'formatted' => number_format($this->withdrawableMinorUnit / 100, 2),
            ],
            'currency' => $this->currencyCode,
        ];
    }
}
