<?php

namespace App\Actions\Driver;

use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class MarkDriverArrived
{
    /**
     * Maximum allowed distance in meters between driver and store to trigger arrival.
     */
    protected const GEOFENCE_RADIUS_METERS = 200.0;

    public function execute(User $driver, SubOrder $subOrder, float $lat, float $lng): SubOrder
    {
        return DB::transaction(function () use ($driver, $subOrder, $lat, $lng) {

            $order = $subOrder->order;

            // Guard 1: Ownership Validation
            if ($order->driver_id !== $driver->id) {
                throw new AccessDeniedHttpException('Unauthorized: You are not assigned to this mission.');
            }

            // Guard 2: State Machine Integrity
            // They can only arrive if the vendor has accepted it and they are currently assigned
            if ($subOrder->status !== 'accepted') {
                throw new UnprocessableEntityHttpException('Invalid state transition: Order is not ready for pickup.');
            }

            // Guard 3: Spatial Geofence Verification via PostGIS
            $store = $subOrder->store;

            if (!$store->latitude || !$store->longitude) {
                throw new UnprocessableEntityHttpException('Store location data is missing. Cannot verify proximity.');
            }

            // Use ST_DistanceSphere to calculate exact distance in meters on the Earth's curvature
            $distanceMeters = (float) DB::scalar(
                "SELECT ST_DistanceSphere(ST_GeomFromText(?), ST_GeomFromText(?))",
                [
                    "POINT({$lng} {$lat})",
                    "POINT({$store->longitude} {$store->latitude})"
                ]
            );

            if ($distanceMeters > self::GEOFENCE_RADIUS_METERS) {
                throw new UnprocessableEntityHttpException(
                    "You are too far away to mark as arrived. Current distance: " . round($distanceMeters) . "m."
                );
            }

            // Mutation: Lock in the arrival state
            $subOrder->update([
                'status' => 'driver_arrived'
            ]);


             broadcast(new \App\Events\DriverArrivedAtStore($subOrder));

            return $subOrder;
        });
    }
}
