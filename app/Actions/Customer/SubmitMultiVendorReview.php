<?php

namespace App\Actions\Customer;

use App\Models\Order;
use App\Models\User;
use App\Models\DriverReview;
use App\Models\StoreReview;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubmitMultiVendorReview
{
    /**
     * Store reviews for both logistics personnel and merchant entities.
     */
    public function execute(Order $order, User $customer, array $data): void
    {
        // 1. Guard against illicit multi-tenant access or lifecycle state violations
        if ($order->user_id !== $customer->id) {
            throw new RuntimeException('Unauthorized: You do not own this transaction record.');
        }

        if ($order->status !== 'delivered') {
            throw new RuntimeException('Review vectors remain locked until the payload is successfully delivered.');
        }

        // 2. Prevent duplicate execution paths
        $alreadyReviewed = DB::table('store_reviews')
            ->whereIn('sub_order_id', $order->subOrders()->pluck('id'))
            ->exists();

        if ($alreadyReviewed) {
            throw new RuntimeException('This multi-vendor order has already been rated.');
        }

        // 3. Atomically write reviews to the database
        DB::transaction(function () use ($order, $customer, $data) {

            // Step A: Persist Driver evaluation metrics if a driver was assigned and rated
            if (!empty($data['driver_rating']) && $order->driver_id) {
                DriverReview::create([
                    'order_id' => $order->id,
                    'user_id' => $customer->id,
                    'driver_id' => $order->driver_id,
                    'rating' => $data['driver_rating'],
                    'comment' => $data['driver_comment'] ?? null,
                ]);
            }

            // Step B: Iterate and log distinct scores for each merchant sub-basket
            foreach ($data['store_reviews'] as $storeReviewData) {
                // Find the associated sub-order to resolve the exact store context mapping
                $subOrder = $order->subOrders()->findOrFail($storeReviewData['sub_order_id']);

                StoreReview::create([
                    'sub_order_id' => $subOrder->id,
                    'store_id' => $subOrder->store_id,
                    'user_id' => $customer->id,
                    'rating' => $storeReviewData['rating'],
                    'comment' => $storeReviewData['comment'] ?? null,
                ]);
            }
        });
    }
}
