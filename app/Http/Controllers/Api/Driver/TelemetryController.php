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
        // 1. Resolve the Order Model to satisfy your existing Event constructor
        $order = Order::findOrFail($request->order_id);

        // 2. Broadcast securely straight to the customer over WebSockets
        broadcast(new DriverLocationUpdated(
            $order,
            (float) $request->latitude,
            (float) $request->longitude
        ));

        // 3. Append to the Redis Breadcrumb List queue (Lightning Fast)
        Redis::rpush('telemetry:breadcrumbs', json_encode([
            'order_id'    => $order->id,
            'latitude'    => (float) $request->latitude,
            'longitude'   => (float) $request->longitude,
            'recorded_at' => now()->toDateTimeString(),
        ]));

        return response()->json(['status' => 'broadcasted_and_queued'], 200);
    }
}
