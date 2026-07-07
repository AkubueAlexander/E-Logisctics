<?php

namespace App\Actions\Driver;

use App\Models\SubOrder;
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
     * and transition lifecycle states within a safe database transaction.
     */
    public function execute(User $driver, SubOrder $subOrder, float $lat, float $lng, string $otp): SubOrder
    {
        return DB::transaction(function () use ($driver, $subOrder, $lat, $lng, $otp) {
            $order = $subOrder->order;

            // Guard 1: Ownership Verification
            if ($order->driver_id !== $driver->id) {
                throw new AccessDeniedHttpException('Unauthorized: You are not assigned to this mission.');
            }

            // Guard 2: Strict Workflow State Enforcement
            if ($subOrder->status !== 'in_transit') {
                throw new UnprocessableEntityHttpException(
                    "Cannot mark as delivered. Current status is '{$subOrder->status}', expected 'in_transit'."
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
                throw new UnprocessableEntityHttpException('Customer delivery coordinates are missing from the master order.');
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

            // 1. Advance Individual SubOrder Status
            $subOrder->update([
                'status' => 'delivered'
            ]);

            // 2. Evaluate and Synchronize Parent Master Order Lifecycle
            $allDelivered = $order->subOrders()->where('status', '!=', 'delivered')->doesntExist();
            $masterOrderCompleted = false;

            if ($allDelivered && $order->status !== 'completed') {
                $order->update([
                    'status' => 'completed'
                ]);

                // Free up driver availability mapping for immediate dispatch loop matching
                $driver->driverProfile()->update([
                    'availability_status' => 'available'
                ]);

                // Tear down operational tracking components
                if ($order->deliveryMission) {
                    $order->deliveryMission()->update([
                        'status' => 'completed'
                    ]);
                }

                $masterOrderCompleted = true;

                // Evict token from memory since the master delivery chain is securely finalized
                Cache::store('redis')->forget($cacheKey);
            }

            // 3. Dispatch operational event out of the transaction lifecycle to handle financial computations
            event(new SubOrderDeliveredEvent($subOrder, $masterOrderCompleted));

            return $subOrder;
        });
    }
}
