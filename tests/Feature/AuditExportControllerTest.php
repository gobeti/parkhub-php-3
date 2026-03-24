<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function enableModules(): void
    {
        config(['modules.audit_log' => true, 'modules.audit_export' => true]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seedEntries(): void
    {
        AuditLog::log(['action' => 'LoginSuccess', 'event_type' => 'LoginSuccess', 'username' => 'admin', 'ip_address' => '10.0.0.1']);
        AuditLog::log(['action' => 'BookingCreated', 'event_type' => 'BookingCreated', 'username' => 'alice', 'ip_address' => '10.0.0.2']);
        AuditLog::log(['action' => 'SettingsChanged', 'event_type' => 'SettingsChanged', 'username' => 'admin', 'ip_address' => '10.0.0.1']);
    }

    public function test_enhanced_export_csv(): void
    {
        $this->enableModules();
        $admin = $this->adminUser();
        $this->seedEntries();

        $response = $this->actingAs($admin)->get('/api/v1/admin/audit-log/export/enhanced?format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_enhanced_export_json(): void
    {
        $this->enableModules();
        $admin = $this->adminUser();
        $this->seedEntries();

        $response = $this->actingAs($admin)->get('/api/v1/admin/audit-log/export/enhanced?format=json');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json; charset=UTF-8');
    }

    public function test_enhanced_export_pdf(): void
    {
        $this->enableModules();
        $admin = $this->adminUser();
        $this->seedEntries();

        $response = $this->actingAs($admin)->get('/api/v1/admin/audit-log/export/enhanced?format=pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('AUDIT LOG EXPORT', $response->streamedContent());
    }

    public function test_enhanced_export_invalid_format(): void
    {
        $this->enableModules();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-log/export/enhanced?format=xml');

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    public function test_enhanced_export_filters_by_action(): void
    {
        $this->enableModules();
        $admin = $this->adminUser();
        $this->seedEntries();

        $response = $this->actingAs($admin)->get('/api/v1/admin/audit-log/export/enhanced?format=json&action=LoginSuccess');

        $response->assertOk();
        $content = $response->streamedContent();
        $data = json_decode($content, true);
        $this->assertEquals(1, $data['count']);
    }

    public function test_enhanced_export_filters_by_date_range(): void
    {
        $this->enableModules();
        $admin = $this->adminUser();
        $this->seedEntries();

        $today = date('Y-m-d');
        $response = $this->actingAs($admin)->get("/api/v1/admin/audit-log/export/enhanced?format=json&from={$today}&to={$today}");

        $response->assertOk();
        $content = $response->streamedContent();
        $data = json_decode($content, true);
        $this->assertGreaterThanOrEqual(1, $data['count']);
    }

    public function test_enhanced_export_requires_admin(): void
    {
        $this->enableModules();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/api/v1/admin/audit-log/export/enhanced?format=csv')->assertForbidden();
    }

    public function test_enhanced_export_module_disabled_returns_404(): void
    {
        config(['modules.audit_log' => true, 'modules.audit_export' => false]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->get('/api/v1/admin/audit-log/export/enhanced?format=csv')->assertNotFound();
    }
}
