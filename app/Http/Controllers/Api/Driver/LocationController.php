<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Events\DriverLocationUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use App\Models\Order;

class LocationController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        // Now validating an array of telemetry points
        $validated = $request->validate([
            'active_order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'telemetry' => ['required', 'array', 'min:1', 'max:200'], // Max batch size to prevent payload bloat
            'telemetry.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'telemetry.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'telemetry.*.heading' => ['nullable', 'numeric'],
            'telemetry.*.recorded_at' => ['required', 'date'], // The exact time the phone got the GPS ping
        ]);

        $orderId = $validated['active_order_id'] ?? null;
        $telemetryBatch = $validated['telemetry'];
        
        // Grab the most recent point in the batch for the live map update
        // (Assuming the frontend sends them chronologically, grab the last one)
        $latestPoint = end($telemetryBatch); 
        $heading = $latestPoint['heading'] ?? 0.0;

        if ($orderId) {
            $order = Order::find($orderId);
            
            // 1. Broadcast ONLY the latest location to the customer's live map
            broadcast(new DriverLocationUpdated(
                $request->user()->id, 
                $order->id, 
                (float) $latestPoint['latitude'], 
                (float) $latestPoint['longitude'],
                (float) $heading
            ));

            // 2. Loop through all queued points and push the entire history to Redis
            foreach ($telemetryBatch as $point) {
                Redis::rpush('telemetry:breadcrumbs', json_encode([
                    'order_id'    => $order->id,
                    'latitude'    => (float) $point['latitude'],
                    'longitude'   => (float) $point['longitude'],
                    'heading'     => (float) ($point['heading'] ?? 0.0),
                    'recorded_at' => \Carbon\Carbon::parse($point['recorded_at'])->toDateTimeString(), // Trusting mobile time
                ]));
            }
        }

        // 3. Update current profile location with the most recent ping
        Redis::set("driver:{$request->user()->id}:location", json_encode([
            'lat' => $latestPoint['latitude'],
            'lng' => $latestPoint['longitude'],
            'updated_at' => now()->timestamp
        ]));

        return response()->json(['message' => 'Batch synced successfully.'], 200);
    }
}