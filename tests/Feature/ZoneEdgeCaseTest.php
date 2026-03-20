<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndLot(string $role = 'admin'): array
    {
        $user = User::factory()->create(['role' => $role]);
        $lot = ParkingLot::create([
            'name' => 'Zone Edge Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);

        return [$user, $lot];
    }

    public function test_zone_update(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $zone = Zone::create(['lot_id' => $lot->id, 'name' => 'Old Zone', 'color' => '#000000']);

        // Update/delete routes are on /api/ not /api/v1/
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/lots/'.$lot->id.'/zones/'.$zone->id, [
                'name' => 'Updated Zone',
                'color' => '#FF0000',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('zones', [
            'id' => $zone->id,
            'name' => 'Updated Zone',
            'color' => '#FF0000',
        ]);
    }

    public function test_zone_delete(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $zone = Zone::create(['lot_id' => $lot->id, 'name' => 'Delete Zone']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/lots/'.$lot->id.'/zones/'.$zone->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('zones', ['id' => $zone->id]);
    }

    public function test_zone_delete_nonexistent_returns_404(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/lots/'.$lot->id.'/zones/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    public function test_zone_update_nonexistent_returns_404(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/lots/'.$lot->id.'/zones/00000000-0000-0000-0000-000000000000', [
                'name' => 'Ghost',
            ])
            ->assertStatus(404);
    }

    public function test_zone_from_other_lot_not_accessible(): void
    {
        [$user, $lot1] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $lot2 = ParkingLot::create([
            'name' => 'Other Zone Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);

        $zone = Zone::create(['lot_id' => $lot2->id, 'name' => 'Lot2 Zone']);

        // Try to update zone via wrong lot
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/lots/'.$lot1->id.'/zones/'.$zone->id, ['name' => 'Hacked'])
            ->assertStatus(404);

        // Zone name should be unchanged
        $this->assertDatabaseHas('zones', ['id' => $zone->id, 'name' => 'Lot2 Zone']);
    }

    public function test_zone_create_duplicate_name_same_lot(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        Zone::create(['lot_id' => $lot->id, 'name' => 'VIP']);

        // Creating another zone with the same name in the same lot should be allowed
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/lots/'.$lot->id.'/zones', [
                'name' => 'VIP',
                'color' => '#0000FF',
            ]);

        $response->assertStatus(201);
        $this->assertEquals(2, Zone::where('lot_id', $lot->id)->where('name', 'VIP')->count());
    }

    public function test_zone_create_without_color(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/lots/'.$lot->id.'/zones', [
                'name' => 'Colorless Zone',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('zones', ['name' => 'Colorless Zone']);
    }

    public function test_unauthenticated_zone_access_rejected(): void
    {
        [$user, $lot] = $this->createUserAndLot();

        $this->getJson('/api/v1/lots/'.$lot->id.'/zones')
            ->assertStatus(401);
    }

    public function test_zone_for_nonexistent_lot_returns_empty(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots/'.$fakeId.'/zones');

        $response->assertStatus(200);
    }
}
