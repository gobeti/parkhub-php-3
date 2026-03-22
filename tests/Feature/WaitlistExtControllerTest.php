<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistExtControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUserLotSlot(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Waitlist Ext Lot',
            'total_slots' => 5,
            'available_slots' => 0,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'A1',
            'status' => 'available',
        ]);

        return [$user, $lot, $slot];
    }

    public function test_subscribe_to_waitlist(): void
    {
        [$user, $lot] = $this->createUserLotSlot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/lots/{$lot->id}/waitlist/subscribe", ['priority' => 2]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.priority', 2);
    }

    public function test_subscribe_duplicate_returns_conflict(): void
    {
        [$user, $lot] = $this->createUserLotSlot();
        $token = $user->createToken('test')->plainTextToken;

        WaitlistEntry::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'status' => 'waiting',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/lots/{$lot->id}/waitlist/subscribe");

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'ALREADY_ON_WAITLIST');
    }

    public function test_view_lot_waitlist_position(): void
    {
        [$user, $lot] = $this->createUserLotSlot();
        $token = $user->createToken('test')->plainTextToken;

        WaitlistEntry::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'status' => 'waiting',
            'priority' => 3,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/lots/{$lot->id}/waitlist");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.entries.0.position', 1)
            ->assertJsonPath('data.entries.0.total_ahead', 0);
    }

    public function test_leave_waitlist(): void
    {
        [$user, $lot] = $this->createUserLotSlot();
        $token = $user->createToken('test')->plainTextToken;

        WaitlistEntry::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'status' => 'waiting',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/lots/{$lot->id}/waitlist");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('waitlist_entries', [
            'user_id' => $user->id,
            'lot_id' => $lot->id,
        ]);
    }

    public function test_leave_waitlist_not_found(): void
    {
        [$user, $lot] = $this->createUserLotSlot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/lots/{$lot->id}/waitlist");

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_ON_WAITLIST');
    }

    public function test_accept_offered_slot(): void
    {
        [$user, $lot, $slot] = $this->createUserLotSlot();
        $token = $user->createToken('test')->plainTextToken;

        $entry = WaitlistEntry::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'status' => 'offered',
            'notified_at' => now(),
            'offer_expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/lots/{$lot->id}/waitlist/{$entry->id}/accept");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.entry.status', 'accepted');

        $this->assertNotNull($response->json('data.booking_id'));
    }

    public function test_accept_non_offered_returns_error(): void
    {
        [$user, $lot] = $this->createUserLotSlot();
        $token = $user->createToken('test')->plainTextToken;

        $entry = WaitlistEntry::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'status' => 'waiting',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/lots/{$lot->id}/waitlist/{$entry->id}/accept");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_OFFERED');
    }

    public function test_decline_offered_slot(): void
    {
        [$user, $lot] = $this->createUserLotSlot();
        $token = $user->createToken('test')->plainTextToken;

        $entry = WaitlistEntry::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'status' => 'offered',
            'notified_at' => now(),
            'offer_expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/lots/{$lot->id}/waitlist/{$entry->id}/decline");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'declined');
    }

    public function test_decline_promotes_next_in_queue(): void
    {
        [$user1, $lot] = $this->createUserLotSlot();
        $user2 = User::factory()->create(['role' => 'user']);

        $entry1 = WaitlistEntry::create([
            'user_id' => $user1->id,
            'lot_id' => $lot->id,
            'status' => 'offered',
            'notified_at' => now(),
            'offer_expires_at' => now()->addMinutes(15),
        ]);

        WaitlistEntry::create([
            'user_id' => $user2->id,
            'lot_id' => $lot->id,
            'status' => 'waiting',
            'priority' => 3,
        ]);

        $token = $user1->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/lots/{$lot->id}/waitlist/{$entry1->id}/decline")
            ->assertStatus(200);

        // Next user should now be offered
        $this->assertDatabaseHas('waitlist_entries', [
            'user_id' => $user2->id,
            'lot_id' => $lot->id,
            'status' => 'offered',
        ]);
    }

    public function test_unauthenticated_cannot_subscribe(): void
    {
        [$user, $lot] = $this->createUserLotSlot();

        $response = $this->postJson("/api/v1/lots/{$lot->id}/waitlist/subscribe");

        $response->assertStatus(401);
    }
}
