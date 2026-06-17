<?php

namespace App\Services;

use App\Models\Order;
use App\Models\DriverProfile;
use Illuminate\Support\Collection;

class DispatchService
{
    /**
     * Find the closest available drivers to a specific coordinate.
     */
    public function findNearbyDrivers(float $originLat, float $originLng, int $radiusKm = 5, int $limit = 3): Collection
    {
        // Haversine formula to calculate true Earth-surface distance in kilometers
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(current_latitude)) * cos(radians(current_longitude) - radians(?)) + sin(radians(?)) * sin(radians(current_latitude))))";

        return DriverProfile::query()
            ->select('driver_profiles.*')
            ->selectRaw("{$haversine} AS distance", [$originLat, $originLng, $originLat])
            ->where('availability_status', 'available')
            ->where('verification_status', 'verified')
            ->whereRaw("{$haversine} <= ?", [$originLat, $originLng, $originLat, $radiusKm])
            ->orderBy('distance')
            ->limit($limit)
            ->get();
    }

    /**
     * Triggered when an order becomes 'ready_for_pickup'.
     */
    public function dispatchOrder(Order $order): void
    {
        // In a multi-store order, we take the latitude/longitude of the first store as the starting hub
        $primaryStore = $order->subOrders()->first()->store;

        $closestDrivers = $this->findNearbyDrivers($primaryStore->latitude, $primaryStore->longitude);

        if ($closestDrivers->isEmpty()) {
            // No drivers nearby. In a real system, you'd dispatch a delayed Job to try again in 1 minute.
            return;
        }

        // Push a notification/Reverb event to these specific drivers inviting them to accept the order
        foreach ($closestDrivers as $driver) {
            // e.g., broadcast(new OrderAvailableForDriver($order, $driver));
        }
    }
}
