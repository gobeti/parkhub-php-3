<?php

namespace Tests\Feature;

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
}
