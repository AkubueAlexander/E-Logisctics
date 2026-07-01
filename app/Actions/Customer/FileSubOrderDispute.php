<?php

namespace App\Actions\Customer;

use App\Models\Order;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class `FileSubOrderDispute
{
    /**
     * Flag a vendor sub-basket as disputed and safely freeze the corresponding wallet payout.
     */
    public function execute(User $customer, array $data): SubOrder
    {
        return DB::transaction(function () use ($customer, $data) {

            // 1. Fetch the targeted sub-order and its orchestration parent
            $subOrder = SubOrder::with('order')->findOrFail($data['sub_order_id']);
            $parentOrder = $subOrder->order;

            // 2. Guard Clause: Multi-tenant security check
            if ($parentOrder->user_id !== $customer->id) {
                throw new RuntimeException('Unauthorized: You do not own the parent transaction for this sub-order.');
            }

            // 3. Guard Clause: Lifecycle condition check
            if ($parentOrder->status !== 'delivered') {
                throw new RuntimeException('Disputes can only be raised after an order has been marked as delivered.');
            }

            // 4. Guard Clause: Strict 24-hour dispute window enforcement
            if ($parentOrder->updated_at->addHours(24)->isPast()) {
                throw new RuntimeException('The 24-hour window to file a dispute for this order has expired.');
            }

            // 5. Guard Clause: Avoid double jeopardy
            if ($subOrder->status === 'disputed') {
                throw new RuntimeException('A dispute has already been filed for this specific merchant basket.');
            }

            // 6. Pivot the logistics status to 'disputed' for administrative indexing
            $subOrder->update([
                'status' => 'disputed',
                'metadata' => json_encode([
                    'dispute_reason' => $data['reason_category'],
                    'customer_explanation' => $data['customer_notes'],
                    'opened_at' => now()->toIso8601String()
                ])
            ]);

            // 7. SURGICAL LEDGER FREEZE: Look up the exact payout row meant for this merchant and lock it
            $frozenRows = Ledger::query()
                ->where('order_id', $parentOrder->id)
                ->where('sub_order_id', $subOrder->id)
                ->where('transaction_type', 'vendor_payout')
                ->update([
                    'status' => 'disputed', // Freezes this specific row so the vendor's wallet balance drops instantly
                ]);

            if ($frozenRows === 0) {
                throw new RuntimeException('Critical: Corresponding financial distribution ledger line not found.');
            }

            return $subOrder;
        });
    }
}
