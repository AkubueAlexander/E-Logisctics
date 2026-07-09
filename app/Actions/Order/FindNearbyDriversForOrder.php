<?php

namespace App\Actions\Order;

use App\Models\Order;
use Illuminate\Support\Collection;
use App\Models\DriverProfile;

class FindNearbyDriversForOrder
{
    /**
     * Locate all eligible drivers within a 5km radius of the order origins.
     */
    public function execute(Order $order, float $radiusKm = 5.0): Collection
    {
        // Eager load sub-orders and store coordinates
        $order->load(['subOrders.store', 'latestDeliveryMission']);

        $mission = $order->latestDeliveryMission;
        $firstStore = $order->subOrders->first()?->store;

        if (!$firstStore || !$firstStore->latitude || !$firstStore->longitude) {
            return collect();
        }

        // Base Query targeting online/available couriers within the radius bubble
        $query = DriverProfile::with('user')
            ->where('availability_status', 'available')
            ->withinRadius($firstStore->latitude, $firstStore->longitude, $radiusKm);

        // If the mission exists, explicitly exclude drivers who already timed out or rejected it
        if ($mission) {
            $query->whereDoesntHave('missionPings', function ($pingQuery) use ($mission) {
                $pingQuery->where('delivery_mission_id', $mission->id)
                    ->whereIn('status', ['timed_out', 'cancelled', 'rejected']);
            });
        }

        // Return ordered by distance proximity from the pickup anchor point
        return $query->orderByRaw(
            "ST_DistanceSphere(location, ST_GeomFromText(?, 4326)) ASC",
            ["POINT({$firstStore->longitude} {$firstStore->latitude})"]
        )->get();
    }
}
