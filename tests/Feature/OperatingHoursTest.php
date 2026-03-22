<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingHoursTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    // ── GET /api/v1/lots/{id}/hours ───────────────────────────────────────

    public function test_get_hours_returns_defaults_when_not_set(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Hours Lot', 'total_slots' => 5]);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson("/api/v1/lots/{$lot->id}/hours");

        $response->assertOk()
            ->assertJsonPath('data.is_24h', true)
            ->assertJsonStructure(['data' => [
                'is_24h', 'is_open_now',
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
            ]]);
    }

    public function test_get_hours_returns_configured_schedule(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Configured Lot',
            'total_slots' => 5,
            'operating_hours' => [
                'is_24h' => false,
                'monday' => ['open' => '08:00', 'close' => '18:00', 'closed' => false],
                'saturday' => ['open' => '09:00', 'close' => '14:00', 'closed' => false],
                'sunday' => ['open' => '00:00', 'close' => '00:00', 'closed' => true],
            ],
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson("/api/v1/lots/{$lot->id}/hours");

        $response->assertOk()
            ->assertJsonPath('data.is_24h', false)
            ->assertJsonPath('data.monday.open', '08:00')
            ->assertJsonPath('data.monday.close', '18:00')
            ->assertJsonPath('data.sunday.closed', true);
    }

    public function test_get_hours_includes_is_open_now(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Open Now Lot', 'total_slots' => 3]);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson("/api/v1/lots/{$lot->id}/hours");

        $response->assertOk();
        $this->assertIsBool($response->json('data.is_open_now'));
    }

    public function test_get_hours_requires_auth(): void
    {
        $lot = ParkingLot::create(['name' => 'Auth Lot', 'total_slots' => 2]);

        $this->getJson("/api/v1/lots/{$lot->id}/hours")
            ->assertStatus(401);
    }

    // ── PUT /api/v1/admin/lots/{id}/hours ─────────────────────────────────

    public function test_admin_update_hours(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = ParkingLot::create(['name' => 'Admin Hours Lot', 'total_slots' => 5]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/v1/admin/lots/{$lot->id}/hours", [
                'is_24h' => false,
                'monday' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
                'sunday' => ['open' => '00:00', 'close' => '00:00', 'closed' => true],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_24h', false)
            ->assertJsonPath('data.monday.open', '09:00')
            ->assertJsonPath('data.monday.close', '17:00')
            ->assertJsonPath('data.sunday.closed', true);

        // Verify persisted
        $lot->refresh();
        $this->assertFalse($lot->operating_hours['is_24h']);
        $this->assertEquals('09:00', $lot->operating_hours['monday']['open']);
    }

    public function test_admin_update_preserves_unset_days(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = ParkingLot::create([
            'name' => 'Preserve Lot',
            'total_slots' => 5,
            'operating_hours' => [
                'is_24h' => false,
                'monday' => ['open' => '08:00', 'close' => '20:00', 'closed' => false],
            ],
        ]);

        // Only update tuesday, monday should be preserved
        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/v1/admin/lots/{$lot->id}/hours", [
                'tuesday' => ['open' => '10:00', 'close' => '16:00', 'closed' => false],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.monday.open', '08:00')
            ->assertJsonPath('data.tuesday.open', '10:00');
    }

    public function test_non_admin_cannot_update_hours(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'NonAdmin Lot', 'total_slots' => 5]);

        $this->withHeaders($this->authHeader($user))
            ->putJson("/api/v1/admin/lots/{$lot->id}/hours", ['is_24h' => false])
            ->assertStatus(403);
    }

    public function test_update_validates_time_format(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = ParkingLot::create(['name' => 'Validate Lot', 'total_slots' => 5]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/v1/admin/lots/{$lot->id}/hours", [
                'monday' => ['open' => 'invalid', 'close' => '17:00'],
            ]);

        $response->assertStatus(422);
    }

    // ── Module disabled ───────────────────────────────────────────────────

    public function test_disabled_module_returns_404(): void
    {
        config(['modules.operating_hours' => false]);

        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Disabled Lot', 'total_slots' => 5]);

        $this->withHeaders($this->authHeader($user))
            ->getJson("/api/v1/lots/{$lot->id}/hours")
            ->assertNotFound();
    }
}
