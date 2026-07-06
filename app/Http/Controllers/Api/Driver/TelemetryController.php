<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\UpdateLocationRequest;
use App\Events\DriverLocationUpdated;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

class TelemetryController extends Controller
{
    public function __invoke(UpdateLocationRequest $request): JsonResponse
    {
        // 1. Resolve the Order Model to verify it exists
        $order = Order::findOrFail($request->order_id);

        // 2. Broadcast securely straight to the customer over WebSockets
        // Aligned to match: int $orderId, float $latitude, float $longitude, float $heading
        broadcast(new DriverLocationUpdated(
            $request->user()->id,
            $order->id,
            (float) $request->latitude,
            (float) $request->longitude,
            (float) $request->heading // Map the missing rotation angle vector
        ));

        // 3. Append to the Redis Breadcrumb List queue (Lightning Fast)
        // Adding heading here preserves the full tracking footprint for your database background worker
        Redis::rpush('telemetry:breadcrumbs', json_encode([
            'order_id'    => $order->id,
            'latitude'    => (float) $request->latitude,
            'longitude'   => (float) $request->longitude,
            'heading'     => (float) $request->heading,
            'recorded_at' => now()->toDateTimeString(),
        ]));

        return response()->json(['status' => 'broadcasted_and_queued'], 200);
    }
}