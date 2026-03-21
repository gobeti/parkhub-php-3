<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GdprTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/users/me/export');

        $response->assertStatus(401);
    }

    public function test_user_can_export_own_data(): void
    {
        $user = User::factory()->create(['role' => 'user', 'department' => 'Engineering']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/me/export');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('profile', $data);
        $this->assertArrayHasKey('bookings', $data);
        $this->assertArrayHasKey('absences', $data);
        $this->assertArrayHasKey('vehicles', $data);
        $this->assertArrayHasKey('preferences', $data);
        $this->assertEquals($user->id, $data['profile']['id']);
    }

    public function test_export_includes_bookings(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Export Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'F1',
            'status' => 'available',
        ]);
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Export Lot',
            'slot_number' => 'F1',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/me/export');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['bookings']);
    }

    public function test_export_includes_vehicles(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'B-AB 1234',
            'make' => 'VW',
            'model' => 'Golf',
            'color' => 'Blue',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/me/export');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['vehicles']);
        $this->assertEquals('B-AB 1234', $data['vehicles'][0]['plate']);
    }

    public function test_export_does_not_include_other_users_data(): void
    {
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);

        Vehicle::create([
            'user_id' => $user2->id,
            'plate' => 'M-XY 9999',
            'make' => 'BMW',
        ]);

        $token = $user1->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/me/export');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(0, $data['vehicles']);
        $this->assertCount(0, $data['bookings']);
    }

    public function test_anonymize_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/users/me/anonymize', ['password' => 'test']);

        $response->assertStatus(401);
    }

    public function test_anonymize_requires_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/users/me/anonymize', ['password' => 'wrongpassword']);

        $response->assertStatus(403);
    }

    public function test_anonymize_account_anonymizes_user_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Hans Mueller',
            'email' => 'hans@example.com',
            'password' => bcrypt('secret123'),
            'department' => 'IT',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/users/me/anonymize', ['password' => 'secret123']);

        $response->assertStatus(200);

        // User record should be anonymized, not deleted
        $anonymized = User::find($user->id);
        $this->assertNotNull($anonymized);
        $this->assertEquals('[Gelöschter Nutzer]', $anonymized->name);
        $this->assertStringContainsString('@deleted.invalid', $anonymized->email);
        $this->assertNull($anonymized->department);
        $this->assertFalse($anonymized->is_active);
    }

    public function test_anonymize_preserves_bookings_with_stripped_pii(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
        ]);
        $lot = ParkingLot::create([
            'name' => 'GDPR Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'G1',
            'status' => 'available',
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
            'vehicle_plate' => 'B-XX 1234',
            'notes' => 'Sensitive note',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/users/me/anonymize', ['password' => 'secret123'])
            ->assertStatus(200);

        // Booking should still exist but PII should be stripped
        $updated = Booking::find($booking->id);
        $this->assertNotNull($updated);
        $this->assertEquals('[GELÖSCHT]', $updated->vehicle_plate);
        $this->assertNull($updated->notes);
    }

    public function test_anonymize_deletes_vehicles(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'B-ZZ 5678',
            'make' => 'Audi',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/users/me/anonymize', ['password' => 'secret123'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('vehicles', ['user_id' => $user->id]);
    }

    public function test_anonymize_deletes_absences(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'homeoffice',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/users/me/anonymize', ['password' => 'secret123'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('absences', ['user_id' => $user->id]);
    }

    public function test_delete_account_removes_user(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/users/me/delete', ['password' => 'password123']);

        $response->assertStatus(200);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_delete_account_requires_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/users/me/delete', ['password' => 'wrong']);

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
