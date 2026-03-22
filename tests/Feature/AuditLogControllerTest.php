<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private function enableModule(): void
    {
        config(['modules.audit_log' => true]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_audit_log_returns_paginated_entries(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        AuditLog::log(['action' => 'LoginSuccess', 'event_type' => 'LoginSuccess', 'username' => 'admin', 'ip_address' => '127.0.0.1']);
        AuditLog::log(['action' => 'BookingCreated', 'event_type' => 'BookingCreated', 'username' => 'alice']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-log');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'entries' => [['id', 'timestamp', 'event_type', 'username']],
                'total',
                'page',
                'per_page',
                'total_pages',
            ],
        ]);
        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_audit_log_filters_by_action(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        AuditLog::log(['action' => 'LoginSuccess', 'event_type' => 'LoginSuccess', 'username' => 'admin']);
        AuditLog::log(['action' => 'BookingCreated', 'event_type' => 'BookingCreated', 'username' => 'bob']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-log?action=LoginSuccess');

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
        $this->assertEquals('LoginSuccess', $response->json('data.entries.0.event_type'));
    }

    public function test_audit_log_filters_by_user(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        AuditLog::log(['action' => 'LoginSuccess', 'event_type' => 'LoginSuccess', 'username' => 'admin']);
        AuditLog::log(['action' => 'LoginSuccess', 'event_type' => 'LoginSuccess', 'username' => 'bob']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-log?user=bob');

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_audit_log_requires_admin(): void
    {
        $this->enableModule();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/api/v1/admin/audit-log')->assertForbidden();
    }

    public function test_audit_log_requires_auth(): void
    {
        $this->enableModule();

        $this->getJson('/api/v1/admin/audit-log')->assertUnauthorized();
    }

    public function test_audit_log_export_returns_csv(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        AuditLog::log(['action' => 'LoginSuccess', 'event_type' => 'LoginSuccess', 'username' => 'admin', 'ip_address' => '10.0.0.1']);

        $response = $this->actingAs($admin)->get('/api/v1/admin/audit-log/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_audit_log_empty_returns_empty_entries(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/audit-log');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.total'));
        $this->assertCount(0, $response->json('data.entries'));
    }

    public function test_audit_log_module_disabled_returns_404(): void
    {
        config(['modules.audit_log' => false]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->getJson('/api/v1/admin/audit-log')->assertNotFound();
    }
}
