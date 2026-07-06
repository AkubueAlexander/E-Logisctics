<?php

namespace App\Actions\Customer;

use App\Models\User;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\OrderItem;
use App\Models\Ledger;
use App\Services\CartService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PlaceMultiVendorOrder
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Executes atomic multi-vendor checkout.
     */
    public function execute(User $customer, array $deliveryDetails): Order
    {
        $cartSummary = $this->cartService->getSummary($customer->id);

        if (empty($cartSummary['stores'])) {
            throw new RuntimeException('Cannot checkout an empty cart structure.');
        }

        $deliveryFeeMinorUnit = 75000;
        $serviceFeeMinorUnit = 15000;
        $basketSubtotalMinorUnit = $cartSummary['grand_total_minor_unit'];
        $grandTotalMinorUnit = $basketSubtotalMinorUnit + $deliveryFeeMinorUnit + $serviceFeeMinorUnit;

        return DB::transaction(function () use (
            $customer,
            $deliveryDetails,
            $cartSummary,
            $deliveryFeeMinorUnit,
            $serviceFeeMinorUnit,
            $grandTotalMinorUnit,
            $basketSubtotalMinorUnit
        ) {


            $txRef = 'GLV-' . strtoupper(Str::random(12));
            // Step A: Store the Parent Order orchestration payload
            $order = Order::create([
                'idempotency_key'           => (string) Str::uuid(),
                'transaction_reference'     => $txRef,
                'customer_id'               => $customer->id,
                'driver_id'                 => null,
                'snapshot_delivery_address' => $deliveryDetails['delivery_address'],
                'snapshot_delivery_latitude'=> $deliveryDetails['latitude'],
                'snapshot_delivery_longitude'=> $deliveryDetails['longitude'],
                'status'                    => 'pending_payment',
                'payment_status'            => 'unpaid',
                'subtotal_minor_unit'       => $basketSubtotalMinorUnit,
                'delivery_fee_minor_unit'   => $deliveryFeeMinorUnit,
                'service_fee_minor_unit'    => $serviceFeeMinorUnit,
                'total_minor_unit'          => $grandTotalMinorUnit,
                'currency_code'             => 'NGN',
            ]);



            // Step C: Route distinct sub-baskets across specific vendor nodes
            foreach ($cartSummary['stores'] as $storeBucket) {

                $commissionMinorUnit = (int) round($storeBucket['store_subtotal_minor_unit'] * 0.125);


                $subOrder = SubOrder::create([
                    'order_id' => $order->id,
                    'store_id' => $storeBucket['store_id'],
                    'status' => 'pending_payment',
                    'subtotal_minor_unit' => $storeBucket['store_subtotal_minor_unit'],
                    'platform_commission_minor_unit' => $commissionMinorUnit,
                    'estimated_prep_time_minutes' => null,
                ]);

                // Step D: Snapshot physical item properties
                foreach ($storeBucket['items'] as $item) {
                    OrderItem::create([
                        'sub_order_id' => $subOrder->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'unit_price_minor_unit' => $item['unit_price_minor_unit'],
                        'total_price_minor_unit' => $item['total_price_minor_unit'],
                        'customizations' => $item['customizations'],
                        'special_instructions' => null,
                    ]);
                }




            }

            $this->cartService->clear($customer->id);

            return $order;
        });
    }
}
