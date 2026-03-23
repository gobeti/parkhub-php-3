<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return $admin->createToken('test')->plainTextToken;
    }

    private function userToken(): string
    {
        $user = User::factory()->create(['role' => 'user']);

        return $user->createToken('test')->plainTextToken;
    }

    public function test_admin_can_list_schedules(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/reports/schedules');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['schedules', 'total']]);
    }

    public function test_regular_user_cannot_access_schedules(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->userToken())
            ->getJson('/api/v1/admin/reports/schedules');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_schedule(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/admin/reports/schedules', [
                'name' => 'Daily Occupancy',
                'report_type' => 'occupancy_summary',
                'frequency' => 'daily',
                'recipients' => ['admin@test.com'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'report_type', 'frequency', 'recipients', 'enabled', 'next_run_at'],
            ])
            ->assertJsonPath('data.name', 'Daily Occupancy')
            ->assertJsonPath('data.enabled', true);
    }

    public function test_create_schedule_validates_report_type(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/admin/reports/schedules', [
                'name' => 'Bad Report',
                'report_type' => 'invalid_type',
                'frequency' => 'daily',
                'recipients' => ['admin@test.com'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_REPORT_TYPE');
    }

    public function test_create_schedule_validates_frequency(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/admin/reports/schedules', [
                'name' => 'Bad Frequency',
                'report_type' => 'occupancy_summary',
                'frequency' => 'hourly',
                'recipients' => ['admin@test.com'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_FREQUENCY');
    }

    public function test_create_schedule_requires_recipients(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/admin/reports/schedules', [
                'name' => 'No Recipients',
                'report_type' => 'occupancy_summary',
                'frequency' => 'daily',
                'recipients' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'RECIPIENTS_REQUIRED');
    }

    public function test_create_schedule_requires_name(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/admin/reports/schedules', [
                'name' => '',
                'report_type' => 'occupancy_summary',
                'frequency' => 'daily',
                'recipients' => ['admin@test.com'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'VALIDATION_ERROR');
    }
}
