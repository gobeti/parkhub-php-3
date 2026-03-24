<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RBACControllerTest extends TestCase
{
    use RefreshDatabase;

    private function enableModule(): void
    {
        config(['modules.rbac' => true]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_list_roles_returns_built_in_roles(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/roles');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [['id', 'name', 'description', 'permissions', 'built_in']],
        ]);

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('super_admin', $names);
        $this->assertContains('admin', $names);
        $this->assertContains('manager', $names);
        $this->assertContains('user', $names);
        $this->assertContains('viewer', $names);
    }

    public function test_create_custom_role(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
            'name' => 'custom_editor',
            'description' => 'Can edit lots and bookings',
            'permissions' => ['manage_lots', 'manage_bookings'],
        ]);

        $response->assertCreated();
        $this->assertEquals('custom_editor', $response->json('data.name'));
        $this->assertFalse($response->json('data.built_in'));
        $this->assertContains('manage_lots', $response->json('data.permissions'));
    }

    public function test_create_duplicate_role_returns_409(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        // First creation
        $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
            'name' => 'test_dup',
            'permissions' => ['view_reports'],
        ]);

        // Duplicate
        $response = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
            'name' => 'test_dup',
            'permissions' => ['view_reports'],
        ]);

        $response->assertStatus(409);
    }

    public function test_update_role_permissions(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $create = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
            'name' => 'updatable_role',
            'permissions' => ['view_reports'],
        ]);

        $id = $create->json('data.id');

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/roles/{$id}", [
            'permissions' => ['view_reports', 'manage_lots', 'manage_settings'],
        ]);

        $response->assertOk();
        $this->assertCount(3, $response->json('data.permissions'));
    }

    public function test_delete_custom_role(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $create = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
            'name' => 'deletable_role',
            'permissions' => ['view_reports'],
        ]);

        $id = $create->json('data.id');

        $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/roles/{$id}");
        $response->assertOk();
    }

    public function test_cannot_delete_built_in_role(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        // Trigger table creation by listing roles
        $this->actingAs($admin)->getJson('/api/v1/admin/roles');

        $builtInRole = DB::table('rbac_roles')->where('built_in', true)->first();
        $this->assertNotNull($builtInRole);

        $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/roles/{$builtInRole->id}");
        $response->assertForbidden();
    }

    public function test_list_permissions(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/permissions');

        $response->assertOk();
        $keys = collect($response->json('data'))->pluck('key')->toArray();
        $this->assertContains('manage_users', $keys);
        $this->assertContains('manage_lots', $keys);
        $this->assertContains('manage_plugins', $keys);
    }

    public function test_assign_and_get_user_roles(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $user = User::factory()->create(['role' => 'user']);

        // Load roles
        $this->actingAs($admin)->getJson('/api/v1/admin/roles');
        $viewerRole = DB::table('rbac_roles')->where('name', 'viewer')->first();

        $this->actingAs($admin)->putJson("/api/v1/admin/users/{$user->id}/roles", [
            'role_ids' => [$viewerRole->id],
        ])->assertOk();

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/users/{$user->id}/roles");
        $response->assertOk();
        $this->assertCount(1, $response->json('data.roles'));
        $this->assertEquals('viewer', $response->json('data.roles.0.name'));
    }

    public function test_rbac_requires_admin(): void
    {
        $this->enableModule();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/api/v1/admin/roles')->assertForbidden();
    }

    public function test_rbac_module_disabled_returns_404(): void
    {
        config(['modules.rbac' => false]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->getJson('/api/v1/admin/roles')->assertNotFound();
    }
}
