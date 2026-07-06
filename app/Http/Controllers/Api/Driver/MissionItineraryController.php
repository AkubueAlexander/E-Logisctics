<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

        $order = $mission->order()->with(['subOrders.store'])->first();
        $stops = [];
        $sequence = 1;

        // 1. Compile all Store Pickups
        foreach ($order->subOrders as $subOrder) {
            $store = $subOrder->store;
            $stops[] = [
                'sequence_order' => $sequence++,
                'type'           => 'pickup',
                'sub_order_id'   => $subOrder->id,
                'name'           => $store->name,
                'address'        => $store->address,
                'latitude'       => $store->latitude,
                'longitude'      => $store->longitude,
                'status'         => $subOrder->status, // e.g., 'ready_for_pickup' or 'picked_up'
            ];
        }

        // 2. Append the Final Customer Dropoff Point using your exact schema columns
        $stops[] = [
            'sequence_order' => $sequence,
            'type'           => 'dropoff',
            'sub_order_id'   => null,
            'name'           => 'Customer Delivery Location',
            'address'        => $order->snapshot_delivery_address,   // Matches snapshot_delivery_address
            'latitude'       => $order->snapshot_delivery_latitude,  // Matches snapshot_delivery_latitude
            'longitude'      => $order->snapshot_delivery_longitude, // Matches snapshot_delivery_longitude
            'status'         => $order->status, // e.g., 'driver_assigned' or 'in_transit'
        ];

        return response()->json([
            'mission_id'   => $mission->id,
            'order_id'     => $order->id,
            'current_step' => $mission->status, // 'picking_up', 'in_transit', etc.
            'itinerary'    => $stops
        ], 200);
    }
}
