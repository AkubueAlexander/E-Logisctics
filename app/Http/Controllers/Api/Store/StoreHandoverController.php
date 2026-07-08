<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\OrderStateTransition;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class StoreHandoverController extends Controller
{
    /**
     * Confirm the driver's identity at the store counter using the single-use OTP token.
     */
    public function __invoke(Request $request, Store $store): JsonResponse
    {
        Gate::authorize('update', $store);
        $validated = $request->validate([
            'sub_order_id'      => ['required', 'integer', 'exists:sub_orders,id'],
            'verification_code' => ['required', 'string', 'size:6'],
        ]);

        // Eager load the parent order to log against its audit trail
        $subOrder = SubOrder::with(['order'])->findOrFail($validated['sub_order_id']);
        $order = $subOrder->order;

        // 1. Verify the 6-digit cache handshake token
        $cacheKey = "sub_order:{$subOrder->id}:pickup_code";
        $storedCode = Cache::get($cacheKey);

        if (!$storedCode || $storedCode !== $validated['verification_code']) {
            return response()->json([
                'message' => 'Verification failed. The 6-digit code is invalid or has expired.'
            ], 422);
        }

        // 2. Clear out the cache key immediately to guarantee it can only be used once
        Cache::forget($cacheKey);

        // 3. Document exactly who confirmed the driver inside the OrderStateTransition log
        OrderStateTransition::create([
            'order_id'             => $order->id,
            'from_status'          => $order->status, // Keeps the parent order status intact
            'to_status'            => $order->status, // Keeps the parent order status intact
            'triggered_by_user_id' => $request->user()->id, // The store employee/manager user ID who verified the code
            'metadata'             => json_encode([
                'context'             => 'Merchant verified driver identity via secure cache handshake token.',
                'sub_order_id'        => $subOrder->id,
                'store_id'            => $subOrder->store_id,
                'verification_method' => 'cache_secure_otp',
                'driver_id'           => $order->driver_id, // Links the confirmed driver at the time of verification
            ]),
        ]);

        return response()->json([
            'message' => 'Driver identity verified successfully.'
        ], 200);
    }
}
