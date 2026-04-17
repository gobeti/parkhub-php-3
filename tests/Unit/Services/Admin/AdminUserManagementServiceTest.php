<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use App\Models\User;
use App\Services\Admin\AdminUserManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AdminUserManagementService
    {
        return app(AdminUserManagementService::class);
    }

    public function test_update_user_bypasses_mass_assignment_for_role_and_invalidates_tokens(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;
        $this->assertNotEmpty($token);
        $this->assertSame(1, $user->tokens()->count());

        $result = $this->service()->updateUser($user, [
            'name' => 'Promoted Admin',
            'role' => 'admin',
        ]);

        $this->assertTrue($result['role_changed']);
        $this->assertFalse($result['password_changed']);
        $this->assertSame('admin', $result['user']->role);
        $this->assertSame('Promoted Admin', $result['user']->name);
        // Token invalidation on privilege change.
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_update_user_rehashes_password_and_invalidates_tokens_when_password_changes(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'password' => Hash::make('old-password'),
        ]);
        $user->createToken('test');

        $result = $this->service()->updateUser($user, [
            'password' => 'brand-new-password',
        ]);

        $this->assertTrue($result['password_changed']);
        $this->assertFalse($result['role_changed']);
        $this->assertTrue(Hash::check('brand-new-password', $result['user']->password));
        $this->assertFalse(Hash::check('old-password', $result['user']->password));
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_update_user_does_not_invalidate_tokens_for_harmless_profile_edits(): void
    {
        $user = User::factory()->create(['role' => 'user', 'name' => 'Alice']);
        $user->createToken('test');
        $this->assertSame(1, $user->tokens()->count());

        $result = $this->service()->updateUser($user, [
            'name' => 'Alice Updated',
            'department' => 'Ops',
        ]);

        $this->assertFalse($result['role_changed']);
        $this->assertFalse($result['password_changed']);
        $this->assertSame(1, $user->fresh()->tokens()->count());
        $this->assertSame('Alice Updated', $result['user']->name);
        $this->assertSame('Ops', $result['user']->department);
    }

    public function test_delete_user_refuses_self_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $result = $this->service()->deleteUser($admin, $admin);

        $this->assertFalse($result);
        $this->assertNotNull(User::find($admin->id));
    }

    public function test_delete_user_removes_target_when_actor_is_different(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);

        $result = $this->service()->deleteUser($target, $admin);

        $this->assertTrue($result);
        // User model uses SoftDeletes — default scope hides it.
        $this->assertNull(User::find($target->id));
        $this->assertNotNull(User::withTrashed()->find($target->id));
    }

    public function test_import_users_skips_existing_usernames_and_emails(): void
    {
        User::factory()->create(['username' => 'alice', 'email' => 'alice@example.com']);
        User::factory()->create(['username' => 'bob', 'email' => 'bob@example.com']);

        $imported = $this->service()->importUsers([
            // Duplicate username — must be skipped.
            ['username' => 'alice', 'email' => 'new-alice@example.com', 'password' => 'password123'],
            // Duplicate email — must be skipped.
            ['username' => 'new-bob', 'email' => 'bob@example.com', 'password' => 'password123'],
            // Fresh row — must be created.
            ['username' => 'carol', 'email' => 'carol@example.com', 'password' => 'password123', 'role' => 'admin'],
        ]);

        $this->assertSame(1, $imported);
        $carol = User::where('username', 'carol')->first();
        $this->assertNotNull($carol);
        $this->assertSame('admin', $carol->role);
        $this->assertSame('carol@example.com', $carol->email);
        // Alice/Bob rows must be untouched.
        $this->assertSame('alice@example.com', User::where('username', 'alice')->value('email'));
        $this->assertSame('bob', User::where('email', 'bob@example.com')->value('username'));
    }

    public function test_bulk_action_skips_self_destructive_actions_on_actor_and_records_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $summary = $this->service()->bulkAction(
            action: 'deactivate',
            userIds: [$admin->id, $target->id, '00000000-0000-0000-0000-000000000000'],
            actor: $admin,
            ip: '198.51.100.7',
        );

        $this->assertSame('deactivate', $summary['action']);
        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['successful']);

        $byUser = collect($summary['results'])->keyBy('user_id');
        $this->assertSame('skipped', $byUser[$admin->id]['status']);
        $this->assertSame('success', $byUser[$target->id]['status']);
        $this->assertSame('failed', $byUser['00000000-0000-0000-0000-000000000000']['status']);

        $this->assertFalse((bool) $target->fresh()->is_active);
        $this->assertTrue((bool) $admin->fresh()->is_active);

        $audit = DB::table('audit_log')
            ->where('user_id', $admin->id)
            ->where('action', 'admin_bulk_deactivate')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('198.51.100.7', $audit->ip_address);
    }

    public function test_bulk_action_change_role_invalidates_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);
        $target->createToken('test');
        $this->assertSame(1, $target->tokens()->count());

        $summary = $this->service()->bulkAction(
            action: 'change_role',
            userIds: [$target->id],
            actor: $admin,
            role: 'premium',
        );

        $this->assertSame(1, $summary['successful']);
        $this->assertSame('premium', $target->fresh()->role);
        $this->assertSame(0, $target->fresh()->tokens()->count());
    }

    public function test_list_users_returns_clamped_pagination(): void
    {
        User::factory()->count(3)->create();

        $page = $this->service()->listUsers(2);

        $this->assertSame(2, $page->perPage());
        $this->assertGreaterThanOrEqual(3, $page->total());
    }
}
