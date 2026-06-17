<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Events\DriverLocationUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'active_order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $driverProfile = $request->user()->driverProfile;

        // 1. Update the driver's current coordinates in the database
        $driverProfile->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
            'location' => DB::raw("ST_GeomFromText('POINT({$validated['longitude']} {$validated['latitude']})', 4326)"),
            'last_location_update' => now(),
        ]);

        // 2. If the driver is currently delivering an order, broadcast their location via Reverb
        if (!empty($validated['active_order_id'])) {
            $order = \App\Models\Order::find($validated['active_order_id']);

            // Fire the Reverb Event
            broadcast(new DriverLocationUpdated($order, $validated['latitude'], $validated['longitude']));
        }

        return response()->json(['message' => 'Location synced.'], 200);
    }
}
