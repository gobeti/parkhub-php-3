<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndLot(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Waitlist Lot',
            'total_slots' => 5,
            'available_slots' => 0,
            'status' => 'open',
        ]);

        return [$user, $lot];
    }

    public function test_unauthenticated_user_cannot_access_waitlist(): void
    {
        $response = $this->getJson('/api/v1/waitlist');
        $response->assertStatus(401);
    }

    public function test_user_can_list_waitlist_entries(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        WaitlistEntry::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/waitlist');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_user_can_add_to_waitlist(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/waitlist', [
                'lot_id' => $lot->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('waitlist_entries', [
            'user_id' => $user->id,
            'lot_id' => $lot->id,
        ]);
    }

    public function test_duplicate_waitlist_entry_is_idempotent(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        // First add
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/waitlist', ['lot_id' => $lot->id])
            ->assertStatus(201);

        // Second add — should not create duplicate
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/waitlist', ['lot_id' => $lot->id])
            ->assertStatus(201);

        $this->assertDatabaseCount('waitlist_entries', 1);
    }

    public function test_waitlist_requires_lot_id(): void
    {
        [$user] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/waitlist', []);

        $response->assertStatus(422);
    }

    public function test_user_can_remove_from_waitlist(): void
    {
        [$user, $lot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $entry = WaitlistEntry::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/waitlist/'.$entry->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('waitlist_entries', ['id' => $entry->id]);
    }

    public function test_user_cannot_remove_another_users_entry(): void
    {
        [$user1, $lot] = $this->createUserAndLot();
        $user2 = User::factory()->create(['role' => 'user']);
        $token2 = $user2->createToken('test')->plainTextToken;

        $entry = WaitlistEntry::create([
            'user_id' => $user1->id,
            'lot_id' => $lot->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token2)
            ->deleteJson('/api/v1/waitlist/'.$entry->id);

        $response->assertStatus(404);
    }

    public function test_empty_waitlist_returns_empty_array(): void
    {
        [$user] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/waitlist');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }
}
