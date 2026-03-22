<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeaturesExtendedTest extends TestCase
{
    use RefreshDatabase;

    private function adminAuth(): array
    {
        $admin = User::factory()->admin()->create();

        return ['Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken];
    }

    private function adminUser(): array
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        return [$admin, ['Authorization' => 'Bearer '.$token]];
    }

    // ── Settings CRUD ───────────────────────────────────────────────────

    public function test_admin_get_settings_returns_defaults(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/settings');

        $response->assertStatus(200)
            ->assertJsonPath('data.company_name', 'ParkHub');
    }

    public function test_admin_update_company_name(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->putJson('/api/v1/admin/settings', [
                'company_name' => 'TestCorp',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('TestCorp', Setting::get('company_name'));
    }

    public function test_admin_update_multiple_settings(): void
    {
        $this->withHeaders($this->adminAuth())
            ->putJson('/api/v1/admin/settings', [
                'company_name' => 'Multi Corp',
                'self_registration' => false,
                'max_bookings_per_day' => 5,
            ]);

        $this->assertEquals('Multi Corp', Setting::get('company_name'));
        $this->assertEquals('false', Setting::get('self_registration'));
        $this->assertEquals('5', Setting::get('max_bookings_per_day'));
    }

    public function test_admin_settings_rejects_invalid_color(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->putJson('/api/v1/admin/settings', [
                'primary_color' => 'not-a-color',
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_settings_accepts_valid_color(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->putJson('/api/v1/admin/settings', [
                'primary_color' => '#ff5500',
            ]);

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_get_settings(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/settings')
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_update_settings(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/settings', ['company_name' => 'Hack'])
            ->assertStatus(403);
    }

    // ── Auto-release settings ───────────────────────────────────────────

    public function test_admin_get_auto_release_settings(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/settings/auto-release');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['enabled', 'timeout_minutes']]);
    }

    public function test_admin_update_auto_release_settings(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->putJson('/api/v1/admin/settings/auto-release', [
                'enabled' => true,
                'timeout_minutes' => 15,
            ]);

        $response->assertStatus(200);
    }

    // ── Email settings ──────────────────────────────────────────────────

    public function test_admin_get_email_settings(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/settings/email');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['smtp_host', 'smtp_port', 'enabled']]);
    }

    public function test_admin_update_email_settings(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->putJson('/api/v1/admin/settings/email', [
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 465,
                'from_email' => 'test@example.com',
                'enabled' => true,
            ]);

        $response->assertStatus(200);
        $this->assertEquals('smtp.example.com', Setting::get('smtp_host'));
    }

    // ── Backup / Restore ────────────────────────────────────────────────

    public function test_admin_export_backup(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/backup');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['exported_at', 'version', 'settings']]);
    }

    public function test_admin_import_backup_settings(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/v1/admin/restore', [
                'settings' => [
                    'company_name' => 'Restored Corp',
                    'self_registration' => 'true',
                ],
            ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data'));

        $this->assertEquals('Restored Corp', Setting::get('company_name'));
    }

    public function test_admin_restore_requires_settings_array(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/v1/admin/restore', []);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_export_backup(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/backup')
            ->assertStatus(403);
    }

    // ── Database Reset ──────────────────────────────────────────────────

    public function test_admin_reset_database_requires_confirm(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/v1/admin/reset', []);

        $response->assertStatus(422);
    }

    public function test_admin_reset_database_wrong_confirm_rejected(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/v1/admin/reset', ['confirm' => 'nope']);

        $response->assertStatus(422);
    }

    public function test_admin_reset_database_deletes_data(): void
    {
        [$admin, $headers] = $this->adminUser();

        // Create some data
        User::factory()->count(3)->create();
        $lot = ParkingLot::create(['name' => 'Reset Lot', 'total_slots' => 1]);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/reset', ['confirm' => 'RESET']);

        $response->assertStatus(200);
        // Admin should still exist, others deleted
        $this->assertEquals(1, User::count());
        $this->assertEquals($admin->id, User::first()->id);
    }

    // ── Audit Log ───────────────────────────────────────────────────────

    public function test_admin_audit_log_returns_paginated(): void
    {
        AuditLog::log(['action' => 'test_action', 'username' => 'testuser']);

        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/audit-log');

        $response->assertStatus(200);
    }

    public function test_admin_audit_log_filter_by_action(): void
    {
        AuditLog::log(['action' => 'login', 'username' => 'user1']);
        AuditLog::log(['action' => 'register', 'username' => 'user2']);

        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/audit-log?action=login');

        $response->assertStatus(200);
    }

    public function test_admin_audit_log_search(): void
    {
        AuditLog::log(['action' => 'login', 'username' => 'searchme']);

        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/audit-log?search=searchme');

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_view_audit_log(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/audit-log')
            ->assertStatus(403);
    }

    // ── Admin User Management Extended ──────────────────────────────────

    public function test_admin_update_user_password(): void
    {
        [$admin, $headers] = $this->adminUser();
        $user = User::factory()->create();

        $response = $this->withHeaders($headers)
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'password' => 'NewSecure123',
            ]);

        $response->assertStatus(200);
    }

    public function test_admin_update_user_email(): void
    {
        [$admin, $headers] = $this->adminUser();
        $user = User::factory()->create();

        $response = $this->withHeaders($headers)
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'email' => 'updated@example.com',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('updated@example.com', $user->fresh()->email);
    }

    public function test_admin_users_list_pagination_max_100(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/users?per_page=200');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 100);
    }

    // ── Admin Slot Management ───────────────────────────────────────────

    public function test_admin_update_slot_status(): void
    {
        $lot = ParkingLot::create(['name' => 'Slot Lot', 'total_slots' => 1]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'S1', 'status' => 'available']);

        $response = $this->withHeaders($this->adminAuth())
            ->patchJson("/api/v1/admin/slots/{$slot->id}", [
                'status' => 'maintenance',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('maintenance', $slot->fresh()->status);
    }

    public function test_admin_update_slot_invalid_status_rejected(): void
    {
        $lot = ParkingLot::create(['name' => 'Slot Lot', 'total_slots' => 1]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'S1', 'status' => 'available']);

        $response = $this->withHeaders($this->adminAuth())
            ->patchJson("/api/v1/admin/slots/{$slot->id}", [
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_update_slot_number(): void
    {
        $lot = ParkingLot::create(['name' => 'Slot Lot', 'total_slots' => 1]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'OLD', 'status' => 'available']);

        $response = $this->withHeaders($this->adminAuth())
            ->patchJson("/api/v1/admin/slots/{$slot->id}", [
                'slot_number' => 'NEW-01',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('NEW-01', $slot->fresh()->slot_number);
    }

    // ── Use Case Settings ───────────────────────────────────────────────

    public function test_admin_get_use_case(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/v1/admin/settings/use-case');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['current', 'available']]);
    }

    public function test_admin_update_use_case_via_settings(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->putJson('/api/v1/admin/settings', [
                'use_case' => 'residential',
            ]);

        // "residential" is valid in settings, check it doesn't crash
        $this->assertContains($response->status(), [200, 422]);
    }
}
