<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_vehicles(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'M-AB 1234',
            'make' => 'BMW',
            'model' => '320d',
            'color' => 'black',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/vehicles');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_only_sees_own_vehicles(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Vehicle::create(['user_id' => $user1->id, 'plate' => 'M-AB 1234']);
        Vehicle::create(['user_id' => $user2->id, 'plate' => 'F-CD 5678']);

        $token = $user1->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/vehicles');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_create_vehicle(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/vehicles', [
                'plate' => 'M-AB 1234',
                'make' => 'BMW',
                'model' => '320d',
                'color' => 'black',
                'is_default' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('vehicles', [
            'user_id' => $user->id,
            'plate' => 'M-AB 1234',
            'make' => 'BMW',
        ]);
    }

    public function test_create_vehicle_requires_plate(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/vehicles', [
                'make' => 'BMW',
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_update_vehicle(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'M-AB 1234',
            'make' => 'BMW',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/vehicles/'.$vehicle->id, [
                'plate' => 'M-XY 9999',
                'color' => 'red',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'plate' => 'M-XY 9999',
            'color' => 'red',
        ]);
    }

    public function test_user_cannot_update_another_users_vehicle(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $vehicle = Vehicle::create([
            'user_id' => $owner->id,
            'plate' => 'M-AB 1234',
        ]);

        $token = $attacker->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/vehicles/'.$vehicle->id, [
                'plate' => 'HACKED',
            ]);

        $response->assertStatus(404);
    }

    public function test_user_can_delete_vehicle(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'M-AB 1234',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/vehicles/'.$vehicle->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    public function test_user_cannot_delete_another_users_vehicle(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $vehicle = Vehicle::create([
            'user_id' => $owner->id,
            'plate' => 'M-AB 1234',
        ]);

        $token = $attacker->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/vehicles/'.$vehicle->id);

        $response->assertStatus(404);
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
    }

    public function test_unauthenticated_user_cannot_access_vehicles(): void
    {
        $response = $this->getJson('/api/v1/vehicles');

        $response->assertStatus(401);
    }
}
