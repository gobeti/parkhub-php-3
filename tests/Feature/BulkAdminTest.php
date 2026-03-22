<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkAdminTest extends TestCase
{
    use RefreshDatabase;

    private function adminAuth(): array
    {
        $admin = User::factory()->admin()->create();

        return ['Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken];
    }

    public function test_bulk_deactivate_users(): void
    {
        $users = User::factory()->count(3)->create();

        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'deactivate',
                'user_ids' => $users->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.action', 'deactivate')
            ->assertJsonPath('data.successful', 3);

        foreach ($users as $user) {
            $this->assertFalse($user->fresh()->is_active);
        }
    }

    public function test_bulk_activate_users(): void
    {
        $users = User::factory()->count(2)->create(['is_active' => false]);

        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'activate',
                'user_ids' => $users->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.successful', 2);

        foreach ($users as $user) {
            $this->assertTrue($user->fresh()->is_active);
        }
    }

    public function test_bulk_change_role(): void
    {
        $users = User::factory()->count(2)->create();

        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'change_role',
                'user_ids' => $users->pluck('id')->toArray(),
                'role' => 'premium',
            ]);

        $response->assertStatus(200);

        foreach ($users as $user) {
            $this->assertEquals('premium', $user->fresh()->role);
        }
    }

    public function test_bulk_delete_users(): void
    {
        $users = User::factory()->count(2)->create();
        $ids = $users->pluck('id')->toArray();

        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'delete',
                'user_ids' => $ids,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.successful', 2);
    }

    public function test_bulk_cannot_deactivate_self(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'deactivate',
                'user_ids' => [$admin->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.successful', 0);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_bulk_rejects_invalid_action(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'invalid',
                'user_ids' => ['some-id'],
            ]);

        $response->assertStatus(422);
    }

    public function test_bulk_requires_user_ids(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'activate',
            ]);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_bulk(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'activate',
                'user_ids' => [],
            ]);

        $response->assertStatus(403);
    }
}
