<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    private function seedBookingData(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Report Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'R1',
            'status' => 'available',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Report Lot',
            'slot_number' => 'R1',
            'start_time' => now()->subHours(2),
            'end_time' => now()->addHours(2),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        return [$admin, $user, $lot, $slot];
    }

    public function test_admin_can_get_reports(): void
    {
        [$admin] = $this->seedBookingData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'period_days',
                    'total_bookings',
                    'by_day',
                    'by_status',
                    'by_booking_type',
                    'avg_duration_hours',
                ],
            ]);
    }

    public function test_admin_reports_with_custom_days_parameter(): void
    {
        [$admin] = $this->seedBookingData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports?days=7');

        $response->assertStatus(200)
            ->assertJsonPath('data.period_days', 7);
    }

    public function test_admin_can_get_heatmap(): void
    {
        [$admin] = $this->seedBookingData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/heatmap');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    public function test_admin_heatmap_with_days_parameter(): void
    {
        [$admin] = $this->seedBookingData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/heatmap?days=14');

        $response->assertStatus(200);
    }

    public function test_admin_can_get_dashboard_charts(): void
    {
        [$admin] = $this->seedBookingData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard/charts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'booking_trend' => ['labels', 'data'],
                    'occupancy_now' => ['total', 'occupied'],
                ],
            ]);
    }

    public function test_dashboard_charts_with_custom_days(): void
    {
        [$admin] = $this->seedBookingData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard/charts?days=14');

        $response->assertStatus(200);
        $labels = $response->json('data.booking_trend.labels');
        $this->assertCount(14, $labels);
    }

    public function test_non_admin_cannot_access_reports(): void
    {
        [, $user] = $this->seedBookingData();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports')
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_access_heatmap(): void
    {
        [, $user] = $this->seedBookingData();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/heatmap')
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_access_dashboard_charts(): void
    {
        [, $user] = $this->seedBookingData();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard/charts')
            ->assertStatus(403);
    }

    public function test_admin_can_export_bookings_csv(): void
    {
        [$admin] = $this->seedBookingData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/admin/bookings/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_non_admin_cannot_export_bookings_csv(): void
    {
        [, $user] = $this->seedBookingData();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/admin/bookings/export')
            ->assertStatus(403);
    }

    public function test_admin_can_export_users_csv(): void
    {
        [$admin] = $this->seedBookingData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/admin/users/export-csv');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
