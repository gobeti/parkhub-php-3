<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_endpoint_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/team');

        $response->assertStatus(401);
    }

    public function test_team_today_endpoint_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/team/today');

        $response->assertStatus(401);
    }

    public function test_user_can_list_team_members(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        User::factory()->count(3)->create(['is_active' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/team');

        $response->assertStatus(200);
        // 4 active users total (the authenticated user + 3 created)
        $response->assertJsonCount(4, 'data');
    }

    public function test_team_list_excludes_inactive_users(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => true, 'name' => 'Active Bob']);
        User::factory()->create(['is_active' => false, 'name' => 'Inactive Eve']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/team');

        $response->assertStatus(200);
        // Only 2 active users (auth user + Active Bob)
        $response->assertJsonCount(2, 'data');
    }

    public function test_team_member_shows_parked_status_when_booked(): void
    {
        $user = User::factory()->create(['is_active' => true, 'name' => 'Parker']);
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'B1',
            'status' => 'available',
        ]);

        // Create an active booking covering "now"
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
            'slot_number' => 'B1',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/team');

        $response->assertStatus(200);
        $data = $response->json('data');
        $member = collect($data)->firstWhere('id', $user->id);
        $this->assertEquals('parked', $member['status']);
    }

    public function test_team_member_shows_absence_type_when_absent(): void
    {
        $user = User::factory()->create(['is_active' => true, 'name' => 'Remote Worker']);

        Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'homeoffice',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/team');

        $response->assertStatus(200);
        $data = $response->json('data');
        $member = collect($data)->firstWhere('id', $user->id);
        $this->assertEquals('homeoffice', $member['status']);
    }

    public function test_team_today_returns_absences_and_bookings(): void
    {
        $user1 = User::factory()->create(['is_active' => true, 'name' => 'Alice']);
        $user2 = User::factory()->create(['is_active' => true, 'name' => 'Bob']);

        // Alice is on vacation today
        Absence::create([
            'user_id' => $user1->id,
            'absence_type' => 'vacation',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        // Bob has a booking today
        $lot = ParkingLot::create([
            'name' => 'Office Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'C1',
            'status' => 'available',
        ]);
        Booking::create([
            'user_id' => $user2->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->startOfDay(),
            'end_time' => now()->endOfDay(),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $token = $user1->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/team/today');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['date', 'absences', 'bookings']]);

        $data = $response->json('data');
        $this->assertCount(1, $data['absences']);
        $this->assertEquals('vacation', $data['absences'][0]['absence_type']);
        $this->assertCount(1, $data['bookings']);
    }
}
