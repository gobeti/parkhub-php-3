<?php

namespace Tests\Feature;

use App\Models\MaintenanceWindow;
use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_username_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422);
    }

    public function test_register_validates_password_complexity(): void
    {
        $this->postJson('/api/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'name' => 'Test User',
        ])
            ->assertStatus(422);
    }

    public function test_register_validates_email_uniqueness(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'username' => 'newuser',
            'email' => 'taken@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'New User',
        ])
            ->assertStatus(422);
    }

    public function test_register_validates_username_uniqueness(): void
    {
        User::factory()->create(['username' => 'existing']);

        $this->postJson('/api/register', [
            'username' => 'existing',
            'email' => 'new@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'New User',
        ])
            ->assertStatus(422);
    }

    public function test_register_succeeds_with_valid_data(): void
    {
        $this->postJson('/api/register', [
            'username' => 'validuser',
            'email' => 'valid@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'Valid User',
        ])
            ->assertStatus(201);
    }

    public function test_booking_requires_lot_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/bookings', [
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ])
            ->assertStatus(422);
    }

    public function test_booking_requires_start_time(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);

        $this->actingAs($user)->postJson('/api/bookings', [
            'lot_id' => $lot->id,
        ])
            ->assertStatus(422);
    }

    public function test_vehicle_requires_plate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/vehicles', [
            'make' => 'BMW',
        ])
            ->assertStatus(422);
    }

    public function test_vehicle_plate_max_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/vehicles', [
            'plate' => str_repeat('X', 21),
        ])
            ->assertStatus(422);
    }

    public function test_vehicle_create_succeeds_with_valid_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/vehicles', [
            'plate' => 'AB-CD-1234',
            'make' => 'BMW',
            'model' => '320i',
            'color' => 'black',
        ])
            ->assertStatus(201);
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'new_password' => 'NewPass123',
        ])
            ->assertStatus(422);
    }

    public function test_change_password_validates_complexity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'password',
            'new_password' => 'nouppercaseornumber',
        ])
            ->assertStatus(422);
    }

    // T-1748 FormRequest migration coverage ─────────────────────────────────

    public function test_grant_credits_requires_amount(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/credits", [])
            ->assertStatus(422);
    }

    public function test_grant_credits_rejects_negative_amount(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/credits", [
                'amount' => -5,
            ])
            ->assertStatus(422);
    }

    public function test_update_user_quota_rejects_over_max(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$target->id}/quota", [
                'monthly_quota' => 10_000,
            ])
            ->assertStatus(422);
    }

    public function test_geofence_update_requires_coordinates(): void
    {
        config(['modules.geofence' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = ParkingLot::create([
            'name' => 'Geo Lot',
            'total_slots' => 1,
            'available_slots' => 1,
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/lots/{$lot->id}/geofence", [
                'radius_meters' => 100,
            ])
            ->assertStatus(422);
    }

    public function test_dynamic_pricing_admin_update_rejects_non_admin(): void
    {
        config(['modules.dynamic_pricing' => true]);
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Dyn Lot',
            'total_slots' => 1,
            'available_slots' => 1,
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/admin/lots/{$lot->id}/pricing/dynamic", [
                'enabled' => true,
            ])
            ->assertStatus(403);
    }

    public function test_maintenance_store_requires_reason(): void
    {
        config(['modules.maintenance' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = ParkingLot::create([
            'name' => 'Maint Lot',
            'total_slots' => 1,
            'available_slots' => 1,
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/maintenance', [
                'lot_id' => $lot->id,
                'start_time' => now()->addDay()->toDateTimeString(),
                'end_time' => now()->addDay()->addHour()->toDateTimeString(),
            ])
            ->assertStatus(422);
    }

    public function test_maintenance_update_validates_lot_id_exists(): void
    {
        config(['modules.maintenance' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = ParkingLot::create([
            'name' => 'Maint Lot',
            'total_slots' => 1,
            'available_slots' => 1,
            'status' => 'open',
        ]);
        $window = MaintenanceWindow::create([
            'lot_id' => $lot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'reason' => 'Test',
            'affected_slots' => ['type' => 'all'],
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/maintenance/{$window->id}", [
                'lot_id' => '00000000-0000-0000-0000-000000000000',
            ])
            ->assertStatus(422);
    }

    public function test_two_factor_disable_requires_password(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/2fa/disable', [])
            ->assertStatus(422);
    }
}
