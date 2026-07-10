<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MissionItineraryController extends Controller
{
    /**
     * Generate the sequential step-by-step task manifest for the driver.
     */
    public function __invoke(Request $request, DeliveryMission $mission): JsonResponse
    {
        // Protect the endpoint: Ensure only the assigned driver can read this manifest
        if ($mission->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized Access to this mission.'], 403);
        }

        $order = $mission->order()->with(['subOrders.store', 'customer'])->first();
        $stops = [];
        $sequence = 1;

        // 1. Compile all Store Pickups
        foreach ($order->subOrders as $subOrder) {
            $store = $subOrder->store;

            // Unique cache key per sub-order package
            $cacheKey = "sub_order:{$subOrder->id}:pickup_code";

            // =========================================================================
            // EXACT PLACE WHERE OTP IS GENERATED AND STORED
            // If code doesn't exist, generate a secure 6-digit padded string & cache it for 24 hours.
            // =========================================================================
            $pickupCode = Cache::remember($cacheKey, now()->addHours(24), function () {
                return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            });

            $stops[] = [
                'sequence_order'            => $sequence++,
                'type'                      => 'pickup',
                'sub_order_id'              => $subOrder->id,
                'name'                      => $store->name,
                'address'                   => $store->address,
                'latitude'                  => $store->latitude,
                'longitude'                 => $store->longitude,
                'status'                    => $subOrder->status,
                'pickup_verification_code'  => $pickupCode, // Sent to driver to present to store manager
            ];
        }

        // 2. Append the Final Customer Dropoff Point
        $stops[] = [
            'sequence_order' => $sequence,
            'type'           => 'dropoff',
            'sub_order_id'   => null,
            'name'           => 'Customer Delivery Location',
            'address'        => $order->snapshot_delivery_address,
            'latitude'       => $order->snapshot_delivery_latitude,
            'longitude'      => $order->snapshot_delivery_longitude,
            'status'         => $order->status,
            'customer_phone' => $order?->customer?->phone_number ?? 'No phone provided'
        ];

        return response()->json([
            'mission_id'   => $mission->id,
            'order_id'     => $order->id,
            'current_step' => $mission->status,
            'itinerary'    => $stops
        ], 200);
    }
}
