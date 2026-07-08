<?php

namespace App\Actions\Driver;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use App\Events\SubOrderDeliveredEvent;

class CompleteDelivery
{
    protected const DELIVERY_GEOFENCE_RADIUS_METERS = 300.0;

    /**
     * Coordinate strict state checks, PostGIS geofencing, Redis OTP verification,
     * and transition lifecycle states.
     */
    public function execute(User $driver, Order $order, float $lat, float $lng, string $otp): Order
    {
        // Array to hold sub-orders so we can fire events outside the transaction
        $deliveredSubOrders = [];

        DB::transaction(function () use ($driver, $order, $lat, $lng, $otp, &$deliveredSubOrders) {

            // Guard 1: Ownership Verification
            if ($order->driver_id !== $driver->id) {
                throw new AccessDeniedHttpException('Unauthorized: You are not assigned to this mission.');
            }

            // Guard 2: Strict Workflow State Enforcement
            if ($order->status !== 'in_transit') {
                throw new UnprocessableEntityHttpException(
                    "Cannot mark as delivered. Current status is '{$order->status}', expected 'in_transit'."
                );
            }

            // Guard 3: Redis Handover OTP Verification
            $cacheKey = "delivery_otp:order:{$order->id}";
            $storedOtp = Cache::store('redis')->get($cacheKey);

            if (!$storedOtp || (string) $storedOtp !== (string) $otp) {
                throw new UnprocessableEntityHttpException('Invalid or expired handover verification code.');
            }

            // Guard 4: Spatial Geofence Verification
            if (!$order->snapshot_delivery_latitude || !$order->snapshot_delivery_longitude) {
                throw new UnprocessableEntityHttpException('Customer delivery coordinates are missing.');
            }

            $distanceMeters = (float) DB::scalar(
                "SELECT ST_DistanceSphere(ST_GeomFromText(?), ST_GeomFromText(?))",
                [
                    "POINT({$lng} {$lat})",
                    "POINT({$order->snapshot_delivery_longitude} {$order->snapshot_delivery_latitude})"
                ]
            );

            if ($distanceMeters > self::DELIVERY_GEOFENCE_RADIUS_METERS) {
                throw new UnprocessableEntityHttpException(
                    "You are too far from the delivery address. Current distance: " . round($distanceMeters) . "m."
                );
            }

            // 1. Process all in-transit sub-orders at once safely

            $subOrders = $order->subOrders()
                ->where('status', 'in_transit')
                ->lockForUpdate()
                ->get();

            foreach ($subOrders as $subOrder) {
                $subOrder->update(['status' => 'delivered']);
                $deliveredSubOrders[] = $subOrder;
            }

            // 2. Finalize Master Order Lifecycle
            $order->update([
                'status' => 'completed'
            ]);

            // 3. Free up driver availability mapping for your dispatch engine
            $driver->driverProfile()->update([
                'availability_status' => 'available'
            ]);

            // 4. Tear down operational tracking components
            if ($order->deliveryMission) {
                $order->deliveryMission()->update([
                    'status' => 'completed'
                ]);
            }

            // 5. Evict OTP token
            Cache::store('redis')->forget($cacheKey);
        });

        // 6. Dispatch events safely OUTSIDE the transaction.

        foreach ($deliveredSubOrders as $subOrder) {
            event(new SubOrderDeliveredEvent($subOrder, true));
        }

        return $order;
    }
}
