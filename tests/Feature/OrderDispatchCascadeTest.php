<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\DriverProfile;
use App\Models\MissionPing;
use App\Actions\Order\UpdateSubOrderState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDispatchCascadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_rejection_automatically_cascades_to_next_nearest_driver(): void
    {
        // 1. Arrange: Create a Customer, an Order, and two nearby Drivers
        $customer = User::factory()->create();

        $order = Order::factory()->create([
            'status' => 'pending_acceptance',
            'customer_id' => $customer->id
        ]);

        $subOrder = SubOrder::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending_acceptance'
        ]);

        // Driver A (Super close to store)
        $driverUserA = User::factory()->create();
        $driverA = DriverProfile::factory()->create([
            'user_id' => $driverUserA->id,
            'availability_status' => 'available',
            // Coordinates set right on the store
        ]);

        // Driver B (A bit further away, but still within 5km)
        $driverUserB = User::factory()->create();
        $driverB = DriverProfile::factory()->create([
            'user_id' => $driverUserB->id,
            'availability_status' => 'available',
            // Coordinates set further out
        ]);

        // 2. Act: Step 1 - Vendor accepts the sub-order, triggering the first dispatch
        app(UpdateSubOrderState::class)->execute($subOrder, ['status' => 'accepted']);

        // Assert parent order moved to searching and a ping was created for Driver A
        $this->assertEquals('searching_for_driver', $order->fresh()->status);

        $firstPing = MissionPing::where('delivery_mission_id', $order->deliveryMission->id)->first();
        $this->assertNotNull($firstPing);
        $this->assertEquals($driverUserA->id, $firstPing->driver_id);
        $this->assertEquals('sent', $firstPing->status);

        // 3. Act: Step 2 - Simulate Driver A hitting your manual rejection endpoint
        $response = $this->actingAs($driverUserA, 'sanctum')
            ->postJson("/api/driver/pings/{$firstPing->id}/reject");

        $response->assertStatus(200);

        // 4. Assert: Verify the loop completed its cascade perfectly
        // First ping must now be marked rejected
        $this->assertEquals('rejected', $firstPing->fresh()->status);

        // A second ping should have been created instantly for Driver B
        $secondPing = MissionPing::where('delivery_mission_id', $order->deliveryMission->id)
            ->where('id', '!=', $firstPing->id)
            ->first();

        $this->assertNotNull($secondPing, "The cascade failed to generate a second ping.");
        $this->assertEquals($driverUserB->id, $secondPing->driver_id, "The dispatch engine did not skip Driver A.");
        $this->assertEquals('sent', $secondPing->status);
    }
}
