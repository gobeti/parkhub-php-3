<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedReportTest extends TestCase
{
    use RefreshDatabase;

    private function adminAuth(): array
    {
        $admin = User::factory()->admin()->create();

        return ['Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken];
    }

    private function createBookingData(): void
    {
        $lot = ParkingLot::create(['name' => 'Report Lot', 'total_slots' => 5, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'R1', 'status' => 'available']);
        $user = User::factory()->create();

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => 'R1',
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDays(2)->addHours(4),
            'status' => 'completed',
            'booking_type' => 'standard',
            'total_price' => 12.50,
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => 'R1',
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHours(2),
            'status' => 'completed',
            'booking_type' => 'standard',
            'total_price' => 8.00,
        ]);
    }

    public function test_revenue_report(): void
    {
        $this->createBookingData();

        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/reports/revenue?start='.now()->subWeek()->format('Y-m-d').'&end='.now()->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['period', 'data', 'total_revenue', 'total_bookings']]);

        $this->assertEquals(20.50, $response->json('data.total_revenue'));
    }

    public function test_revenue_report_group_by_month(): void
    {
        $this->createBookingData();

        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/reports/revenue?start='.now()->subMonth()->format('Y-m-d').'&end='.now()->format('Y-m-d').'&group_by=month');

        $response->assertStatus(200)
            ->assertJsonPath('data.period.group_by', 'month');
    }

    public function test_revenue_requires_dates(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/reports/revenue');

        $response->assertStatus(422);
    }

    public function test_occupancy_report(): void
    {
        $this->createBookingData();

        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/reports/occupancy?start='.now()->subWeek()->format('Y-m-d').'&end='.now()->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['data', 'total_slots']]);
    }

    public function test_users_report(): void
    {
        User::factory()->count(5)->create();

        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/reports/users?days=30');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['total_users', 'new_users', 'registrations_by_day']]);

        // 5 users + admin = 6
        $this->assertEquals(6, $response->json('data.total_users'));
    }

    public function test_reports_require_admin(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/revenue?start=2026-01-01&end=2026-12-31')
            ->assertStatus(403);
    }
}
