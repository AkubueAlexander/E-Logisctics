<?php

namespace App\Actions\Driver;

use App\Models\SubOrder;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CompleteDelivery
{
    protected const DELIVERY_GEOFENCE_RADIUS_METERS = 300.0;

    public function execute(User $driver, SubOrder $subOrder, float $lat, float $lng, ?string $pin = null): SubOrder
    {
        return DB::transaction(function () use ($driver, $subOrder, $lat, $lng, $pin) {

            $order = $subOrder->order;

            // Guard 1: Ownership
            if ($order->driver_id !== $driver->id) {
                throw new AccessDeniedHttpException('Unauthorized: You are not assigned to this mission.');
            }

            // Guard 2: State Enforcement
            if ($subOrder->status !== 'in_transit') {
                throw new UnprocessableEntityHttpException(
                    "Cannot mark as delivered. Current status is '{$subOrder->status}', expected 'in_transit'."
                );
            }

            // Guard 3: Geofence Verification
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

            // 1. Advance SubOrder Status
            $subOrder->update(['status' => 'delivered']);

            // 2. Financial Settlement: Release Vendor & Platform Escrow lines
            Ledger::where('sub_order_id', $subOrder->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'completed',
                    'updated_at' => now()
                ]);

            // 3. Synchronize Parent Order
            $allDelivered = $order->subOrders()->where('status', '!=', 'delivered')->doesntExist();

            if ($allDelivered && $order->status !== 'completed') {

                $order->update(['status' => 'completed']);

                // BOOK DRIVER EARNINGS: Write the completed payout line to the ledger
                Ledger::create([
                    'order_id'          => $order->id,
                    'sub_order_id'      => null,
                    'transaction_type'  => 'driver_payout',
                    'store_id'          => null,
                    'user_id'           => $driver->id, // Assigned to the driver's account
                    'amount_minor_unit' => $order->delivery_fee_minor_unit, // Earns the exact delivery fee
                    'currency_code'     => 'NGN',
                    'status'            => 'completed', // Settled immediately because the delivery is done
                ]);

                // Free up driver availability
                $driver->driverProfile()->update(['availability_status' => 'available']);

                if ($order->deliveryMission) {
                    $order->deliveryMission()->update(['status' => 'completed']);
                }
            }

            return $subOrder;
        });
    }
}
