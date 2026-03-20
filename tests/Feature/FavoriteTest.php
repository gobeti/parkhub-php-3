<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndSlot(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Favorites Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'F1',
            'status' => 'available',
        ]);

        return [$user, $lot, $slot];
    }

    public function test_add_slot_to_favorites(): void
    {
        [$user, $lot, $slot] = $this->createUserAndSlot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/user/favorites', [
                'slot_id' => $slot->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'slot_id' => $slot->id,
        ]);
    }

    public function test_list_favorites(): void
    {
        [$user, $lot, $slot] = $this->createUserAndSlot();
        $token = $user->createToken('test')->plainTextToken;

        Favorite::create(['user_id' => $user->id, 'slot_id' => $slot->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/favorites');

        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();
        $this->assertNotEmpty($data);
    }

    public function test_remove_favorite(): void
    {
        [$user, $lot, $slot] = $this->createUserAndSlot();
        $token = $user->createToken('test')->plainTextToken;

        Favorite::create(['user_id' => $user->id, 'slot_id' => $slot->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/user/favorites/'.$slot->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'slot_id' => $slot->id,
        ]);
    }

    public function test_favorites_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/user/favorites');
        $response->assertStatus(401);
    }

    public function test_add_favorite_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/user/favorites', [
            'slot_id' => fake()->uuid(),
        ]);
        $response->assertStatus(401);
    }

    public function test_add_favorite_requires_slot_id(): void
    {
        [$user] = $this->createUserAndSlot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/user/favorites', []);

        $response->assertStatus(422);
    }

    public function test_adding_same_favorite_twice_is_idempotent(): void
    {
        [$user, $lot, $slot] = $this->createUserAndSlot();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/user/favorites', ['slot_id' => $slot->id])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/user/favorites', ['slot_id' => $slot->id])
            ->assertStatus(201);

        // Should still only have one record
        $this->assertEquals(1, Favorite::where('user_id', $user->id)->where('slot_id', $slot->id)->count());
    }

    public function test_user_only_sees_own_favorites(): void
    {
        [$user1, $lot, $slot] = $this->createUserAndSlot();
        $user2 = User::factory()->create(['role' => 'user']);

        $slot2 = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'F2',
            'status' => 'available',
        ]);

        Favorite::create(['user_id' => $user1->id, 'slot_id' => $slot->id]);
        Favorite::create(['user_id' => $user2->id, 'slot_id' => $slot2->id]);

        $token1 = $user1->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token1)
            ->getJson('/api/v1/user/favorites');

        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();
        // User1 should only see their own favorite, not user2's
        $slotIds = collect($data)->pluck('slot_id')->toArray();
        $this->assertContains($slot->id, $slotIds);
        $this->assertNotContains($slot2->id, $slotIds);
    }
}
