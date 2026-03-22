<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndLot(string $role = 'admin'): array
    {
        $user = User::factory()->create(['role' => $role]);
        $lot = ParkingLot::create([
            'name' => 'Zone Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);

        return [$user, $lot];
    }

    public function test_unauthenticated_cannot_list_zones(): void
    {
        [$user, $lot] = $this->createUserAndLot();

        $response = $this->getJson('/api/v1/lots/'.$lot->id.'/zones');
        $response->assertStatus(401);
    }

    public function test_user_can_list_zones(): void
    {
        [$user, $lot] = $this->createUserAndLot('user');
        $token = $user->createToken('test')->plainTextToken;

        Zone::create(['lot_id' => $lot->id, 'name' => 'Zone A', 'color' => '#FF0000']);
        Zone::create(['lot_id' => $lot->id, 'name' => 'Zone B', 'color' => '#00FF00']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots/'.$lot->id.'/zones');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_create_zone(): void
    {
        [$user, $lot] = $this->createUserAndLot('admin');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/lots/'.$lot->id.'/zones', [
                'name' => 'VIP Zone',
                'color' => '#FFD700',
                'description' => 'Premium parking',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('zones', [
            'lot_id' => $lot->id,
            'name' => 'VIP Zone',
            'color' => '#FFD700',
        ]);
    }

    public function test_zone_requires_name(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/lots/'.$lot->id.'/zones', [
                'color' => '#000000',
            ]);

        $response->assertStatus(422);
    }

    public function test_empty_lot_returns_empty_zones(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots/'.$lot->id.'/zones');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_zone_belongs_to_correct_lot(): void
    {
        [$user, $lot1] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $lot2 = ParkingLot::create([
            'name' => 'Other Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);

        Zone::create(['lot_id' => $lot1->id, 'name' => 'Lot1 Zone']);
        Zone::create(['lot_id' => $lot2->id, 'name' => 'Lot2 Zone']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots/'.$lot1->id.'/zones');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_zone_create_with_description(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/lots/'.$lot->id.'/zones', [
                'name' => 'Electric',
                'color' => '#00FF00',
                'description' => 'EV charging spots',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('zones', [
            'name' => 'Electric',
            'description' => 'EV charging spots',
        ]);
    }
}
