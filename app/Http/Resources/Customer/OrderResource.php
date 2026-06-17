<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status, // Global order state (e.g., preparing, ready_for_pickup, in_transit)
            'delivery_address' => $this->delivery_address,
            'pricing' => [
                'items_total_formatted' => number_format($this->subOrders->sum('items_total_minor_unit') / 100, 2),
                'delivery_fee_formatted' => number_format($this->delivery_fee_minor_unit / 100, 2),
                'currency' => 'NGN',
            ],
            // Driver profile data coupled with their real-time coordinates for the Echo UI
            'driver' => $this->driver_id ? [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'latitude' => (float) $this->driver->driverProfile->current_latitude,
                'longitude' => (float) $this->driver->driverProfile->current_longitude,
            ] : null,
            // Group child baskets cleanly so the customer sees the timeline of each restaurant
            'merchant_baskets' => $this->subOrders->map(function ($subOrder) {
                return [
                    'sub_order_id' => $subOrder->id,
                    'store_name' => $subOrder->store->name,
                    'status' => $subOrder->status, // e.g., accepted, preparing, ready_for_pickup
                    'estimated_prep_time_minutes' => $subOrder->estimated_prep_time_minutes,
                    'items' => $subOrder->items->map(function ($item) {
                        return [
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                        ];
                    }),
                ];
            }),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
