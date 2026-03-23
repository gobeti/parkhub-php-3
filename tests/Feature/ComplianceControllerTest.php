<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceControllerTest extends TestCase
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

    public function test_admin_can_get_compliance_report(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/compliance/report');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'generated_at',
                    'overall_status',
                    'checks' => [
                        '*' => ['id', 'category', 'name', 'description', 'status', 'details', 'recommendation'],
                    ],
                    'data_categories',
                    'legal_basis',
                    'retention_periods',
                    'sub_processors',
                    'tom_summary',
                ],
            ]);
    }

    public function test_compliance_report_has_10_checks(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/compliance/report');

        $response->assertStatus(200);
        $checks = $response->json('data.checks');
        $this->assertCount(10, $checks);
    }

    public function test_compliance_report_has_valid_overall_status(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/compliance/report');

        $response->assertStatus(200);
        $status = $response->json('data.overall_status');
        $this->assertContains($status, ['compliant', 'warning', 'non_compliant']);
    }

    public function test_regular_user_cannot_access_compliance(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->userToken())
            ->getJson('/api/v1/admin/compliance/report');

        $response->assertStatus(403);
    }

    public function test_admin_can_get_data_map(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/compliance/data-map');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'organization',
                    'generated_at',
                    'processing_activities' => [
                        '*' => ['name', 'purpose', 'data_subjects', 'data_categories', 'legal_basis', 'retention', 'recipients'],
                    ],
                ],
            ]);
    }

    public function test_data_map_has_processing_activities(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/compliance/data-map');

        $activities = $response->json('data.processing_activities');
        $this->assertGreaterThanOrEqual(5, count($activities));
    }

    public function test_admin_can_get_audit_export_json(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/compliance/audit-export?format=json');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['format', 'logs', 'count', 'exported_at'],
            ])
            ->assertJsonPath('data.format', 'json');
    }

    public function test_admin_can_get_audit_export_csv(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/compliance/audit-export?format=csv');

        $response->assertStatus(200)
            ->assertJsonPath('data.format', 'csv');

        $csv = $response->json('data.content');
        $this->assertStringContainsString('id,user_id,action', $csv);
    }
}
