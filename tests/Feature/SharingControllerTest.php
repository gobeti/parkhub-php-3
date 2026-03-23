<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Lot;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createBookingForUser(User $user): Booking
    {
        $lot = Lot::factory()->create();
        $slot = Slot::factory()->create(['lot_id' => $lot->id]);

        return Booking::factory()->create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
        ]);
    }

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_user_can_create_share_link(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBookingForUser($user);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson("/api/v1/bookings/{$booking->id}/share", [
                'expires_in_hours' => 168,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'booking_id', 'code', 'url', 'status', 'created_at', 'expires_at', 'view_count'],
            ])
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.booking_id', (string) $booking->id);
    }

    public function test_share_link_returns_404_for_other_users_booking(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $booking = $this->createBookingForUser($owner);

        $response = $this->withHeaders($this->authHeader($other))
            ->postJson("/api/v1/bookings/{$booking->id}/share");

        $response->assertStatus(404)
            ->assertJsonPath('error', 'BOOKING_NOT_FOUND');
    }

    public function test_invite_guest_requires_valid_email(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBookingForUser($user);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson("/api/v1/bookings/{$booking->id}/invite", [
                'email' => 'not-an-email',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_EMAIL');
    }

    public function test_invite_guest_with_valid_email(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBookingForUser($user);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson("/api/v1/bookings/{$booking->id}/invite", [
                'email' => 'guest@example.com',
                'message' => 'Join me!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['invite_id', 'booking_id', 'email', 'sent_at', 'share_url'],
            ])
            ->assertJsonPath('data.email', 'guest@example.com');
    }

    public function test_revoke_share_link(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBookingForUser($user);

        $response = $this->withHeaders($this->authHeader($user))
            ->deleteJson("/api/v1/bookings/{$booking->id}/share");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.revoked', true);
    }

    public function test_share_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/bookings/1/share');

        $response->assertStatus(401);
    }

    public function test_invite_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/bookings/1/invite');

        $response->assertStatus(401);
    }
}
