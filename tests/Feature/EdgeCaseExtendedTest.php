<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdgeCaseExtendedTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    // ── Double Booking Prevention ───────────────────────────────────────

    public function test_double_booking_exact_same_time_rejected(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Double Lot', 'total_slots' => 1]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'D1', 'status' => 'available']);

        $start = now()->addDay()->setHour(9)->format('Y-m-d H:i:s');
        $end = now()->addDay()->setHour(17)->format('Y-m-d H:i:s');

        // First booking
        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => $start,
                'end_time' => $end,
            ])->assertStatus(201);

        $otherUser = User::factory()->create();

        // Second booking same slot same time
        $this->withHeaders($this->authHeader($otherUser))
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => $start,
                'end_time' => $end,
            ])->assertStatus(409);
    }

    public function test_booking_overlap_start_within_existing(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Overlap Lot', 'total_slots' => 1]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'O1', 'status' => 'available']);

        // Existing: 9-17
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay()->setHour(9),
            'end_time' => now()->addDay()->setHour(17),
            'status' => 'confirmed',
        ]);

        $otherUser = User::factory()->create();

        // New: 15-20 (starts within existing)
        $this->withHeaders($this->authHeader($otherUser))
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDay()->setHour(15)->format('Y-m-d H:i:s'),
                'end_time' => now()->addDay()->setHour(20)->format('Y-m-d H:i:s'),
            ])->assertStatus(409);
    }

    public function test_booking_overlap_end_within_existing(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Overlap Lot', 'total_slots' => 1]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'O2', 'status' => 'available']);

        // Existing: 12-18
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay()->setHour(12),
            'end_time' => now()->addDay()->setHour(18),
            'status' => 'confirmed',
        ]);

        $otherUser = User::factory()->create();

        // New: 8-14 (ends within existing)
        $this->withHeaders($this->authHeader($otherUser))
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDay()->setHour(8)->format('Y-m-d H:i:s'),
                'end_time' => now()->addDay()->setHour(14)->format('Y-m-d H:i:s'),
            ])->assertStatus(409);
    }

    // ── Expired / Invalid Tokens ────────────────────────────────────────

    public function test_expired_token_returns_401(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token-string')
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    public function test_missing_auth_header_returns_401(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_deleted_user_token_returns_401(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $user->delete();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    public function test_empty_bearer_token_returns_401(): void
    {
        $this->withHeader('Authorization', 'Bearer ')
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    public function test_malformed_auth_header_returns_401(): void
    {
        $this->withHeader('Authorization', 'NotBearer sometoken')
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    // ── Invalid Dates ───────────────────────────────────────────────────

    public function test_booking_past_date_rejected(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Past Lot', 'total_slots' => 5]);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'P1', 'status' => 'available']);

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'start_time' => now()->subDay()->format('Y-m-d H:i:s'),
                'end_time' => now()->addHour()->format('Y-m-d H:i:s'),
            ])->assertStatus(422);
    }

    public function test_booking_invalid_date_format_rejected(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Format Lot', 'total_slots' => 5]);

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'start_time' => 'not-a-date',
            ])->assertStatus(422);
    }

    // ── Security Headers ────────────────────────────────────────────────

    public function test_security_headers_on_authenticated_endpoint(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/me');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_security_headers_on_error_responses(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    // ── CORS Config ─────────────────────────────────────────────────────

    public function test_cors_credentials_configured(): void
    {
        $cors = config('cors');
        $this->assertArrayHasKey('supports_credentials', $cors);
    }

    public function test_cors_max_age_reasonable(): void
    {
        $cors = config('cors');
        // Max age should be set and not too large
        $this->assertGreaterThan(0, $cors['max_age']);
        $this->assertLessThanOrEqual(86400, $cors['max_age']);
    }

    // ── Public Endpoints ────────────────────────────────────────────────

    public function test_public_occupancy_no_auth(): void
    {
        $this->getJson('/api/v1/public/occupancy')->assertOk();
    }

    public function test_public_display_no_auth(): void
    {
        $this->getJson('/api/v1/public/display')->assertOk();
    }

    public function test_system_version_no_auth(): void
    {
        $this->getJson('/api/v1/system/version')->assertOk();
    }

    public function test_system_maintenance_no_auth(): void
    {
        $this->getJson('/api/v1/system/maintenance')->assertOk();
    }

    public function test_discover_endpoint(): void
    {
        $this->getJson('/api/v1/discover')->assertOk();
    }

    // ── Booking auto-assign edge case ───────────────────────────────────

    public function test_booking_auto_assigns_from_multiple_slots(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Multi Slot Lot', 'total_slots' => 3]);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A2', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A3', 'status' => 'available']);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'start_time' => now()->addDay()->setHour(9)->format('Y-m-d H:i:s'),
                'end_time' => now()->addDay()->setHour(17)->format('Y-m-d H:i:s'),
            ]);

        $response->assertStatus(201);
    }

    // ── User Preferences Edge Cases ─────────────────────────────────────

    public function test_user_preferences_returns_empty_for_new_user(): void
    {
        $user = User::factory()->create(['preferences' => null]);

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/user/preferences')
            ->assertOk();
    }

    public function test_user_preferences_update_theme(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/user/preferences', ['theme' => 'dark'])
            ->assertOk();

        $prefs = $user->fresh()->preferences;
        $this->assertEquals('dark', $prefs['theme']);
    }

    public function test_user_preferences_rejects_invalid_theme(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/user/preferences', ['theme' => 'neon'])
            ->assertStatus(422);
    }

    public function test_user_stats_endpoint(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/user/stats')
            ->assertOk();
    }

    // ── Forgot Password ─────────────────────────────────────────────────

    public function test_forgot_password_returns_generic_message(): void
    {
        // Even for non-existent email, should return same message (prevent enumeration)
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function test_forgot_password_requires_email(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [])
            ->assertStatus(422);
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'not-email',
        ])->assertStatus(422);
    }

    // ── Health Endpoints ────────────────────────────────────────────────

    public function test_health_live_no_auth(): void
    {
        $this->getJson('/api/v1/health/live')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_health_ready_includes_database_status(): void
    {
        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('data.database', 'ok');
    }

    public function test_health_ready_includes_cache_status(): void
    {
        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('data.cache', 'ok');
    }

    // ── Waitlist Edge Cases ─────────────────────────────────────────────

    public function test_waitlist_requires_auth(): void
    {
        $this->getJson('/api/v1/waitlist')->assertStatus(401);
    }

    public function test_waitlist_store_requires_auth(): void
    {
        $this->postJson('/api/v1/waitlist', [])->assertStatus(401);
    }
}
