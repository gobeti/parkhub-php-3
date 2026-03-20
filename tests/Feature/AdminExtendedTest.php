<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExtendedTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        return [$admin, $token];
    }

    public function test_admin_change_user_role_to_user(): void
    {
        [$admin, $token] = $this->createAdmin();
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$user->id, [
                'role' => 'user',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'user']);
    }

    public function test_admin_change_user_role_invalid_value_rejected(): void
    {
        [$admin, $token] = $this->createAdmin();
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$user->id, [
                'role' => 'superduper',
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_deactivate_user(): void
    {
        [$admin, $token] = $this->createAdmin();
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$user->id, [
                'is_active' => false,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
    }

    public function test_admin_update_user_department(): void
    {
        [$admin, $token] = $this->createAdmin();
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$user->id, [
                'department' => 'Engineering',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'department' => 'Engineering']);
    }

    public function test_admin_update_nonexistent_user_returns_404(): void
    {
        [$admin, $token] = $this->createAdmin();
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$fakeId, ['role' => 'admin'])
            ->assertStatus(404);
    }

    public function test_admin_cannot_delete_self(): void
    {
        [$admin, $token] = $this->createAdmin();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/admin/users/'.$admin->id)
            ->assertStatus(400);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_delete_user(): void
    {
        [$admin, $token] = $this->createAdmin();
        $user = User::factory()->create(['role' => 'user']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/admin/users/'.$user->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_list_bookings(): void
    {
        [$admin, $token] = $this->createAdmin();
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create(['name' => 'Admin BK Lot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'AB1', 'status' => 'available']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/bookings');

        $response->assertStatus(200);
    }

    public function test_admin_cancel_booking(): void
    {
        [$admin, $token] = $this->createAdmin();
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create(['name' => 'Admin Cancel Lot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'AC1', 'status' => 'available']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/bookings/'.$booking->id.'/cancel');

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_admin_audit_log(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/audit-log');

        $response->assertStatus(200);
    }

    public function test_admin_user_pagination(): void
    {
        [$admin, $token] = $this->createAdmin();
        User::factory()->count(5)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/users?per_page=3');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 3);
    }

    public function test_non_admin_cannot_list_admin_bookings(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/bookings')
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_cancel_admin_booking(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/bookings/00000000-0000-0000-0000-000000000000/cancel')
            ->assertStatus(403);
    }

    public function test_admin_import_users(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/import', [
                'users' => [
                    [
                        'username' => 'imported1',
                        'email' => 'imported1@example.com',
                        'name' => 'Imported One',
                        'password' => 'SecurePass123',
                    ],
                    [
                        'username' => 'imported2',
                        'email' => 'imported2@example.com',
                        'name' => 'Imported Two',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.imported', 2);

        $this->assertDatabaseHas('users', ['username' => 'imported1']);
        $this->assertDatabaseHas('users', ['username' => 'imported2']);
    }

    public function test_admin_import_skips_existing_users(): void
    {
        [$admin, $token] = $this->createAdmin();
        User::factory()->create(['username' => 'existing', 'email' => 'existing@example.com']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/import', [
                'users' => [
                    [
                        'username' => 'existing',
                        'email' => 'existing@example.com',
                        'name' => 'Existing',
                    ],
                    [
                        'username' => 'newone',
                        'email' => 'newone@example.com',
                        'name' => 'New One',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.imported', 1);
    }

    public function test_admin_delete_lot(): void
    {
        [$admin, $token] = $this->createAdmin();
        $lot = ParkingLot::create(['name' => 'Delete Me', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/admin/lots/'.$lot->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('parking_lots', ['id' => $lot->id]);
    }
}
