<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RateDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function enableModule(): void
    {
        config(['modules.rate_dashboard' => true]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_rate_limits_returns_group_stats(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/rate-limits');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'groups' => [
                    '*' => ['group', 'limit_per_minute', 'description', 'current_count', 'reset_seconds', 'blocked_last_hour'],
                ],
                'total_blocked_last_hour',
            ],
        ]);
    }

    public function test_rate_limits_contains_four_groups(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/rate-limits');

        $response->assertOk();
        $groups = $response->json('data.groups');
        $this->assertCount(4, $groups);

        $groupNames = array_column($groups, 'group');
        $this->assertContains('auth', $groupNames);
        $this->assertContains('api', $groupNames);
        $this->assertContains('public', $groupNames);
        $this->assertContains('webhook', $groupNames);
    }

    public function test_rate_limits_requires_admin(): void
    {
        $this->enableModule();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/api/v1/admin/rate-limits')->assertForbidden();
    }

    public function test_rate_limits_requires_auth(): void
    {
        $this->enableModule();

        $this->getJson('/api/v1/admin/rate-limits')->assertUnauthorized();
    }

    public function test_rate_limits_history_returns_24_bins(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/rate-limits/history');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => ['bins' => ['*' => ['hour', 'count']]],
        ]);

        $bins = $response->json('data.bins');
        $this->assertCount(24, $bins);
    }

    public function test_rate_limits_reflects_cached_blocked_counts(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        Cache::put('rate_limit_blocked:auth', 5, 3600);
        Cache::put('rate_limit_blocked:api', 10, 3600);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/rate-limits');

        $response->assertOk();
        $this->assertEquals(15, $response->json('data.total_blocked_last_hour'));
    }

    public function test_rate_limits_history_reflects_cached_hourly_data(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $key = 'rate_limit_blocked_hour:'.now()->format('Y-m-d-H');
        Cache::put($key, 42, 3600);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/rate-limits/history');

        $response->assertOk();
        $bins = $response->json('data.bins');
        // Last bin should be the current hour with count 42
        $lastBin = end($bins);
        $this->assertEquals(42, $lastBin['count']);
    }

    public function test_rate_limits_module_disabled_returns_404(): void
    {
        config(['modules.rate_dashboard' => false]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->getJson('/api/v1/admin/rate-limits')->assertNotFound();
    }

    public function test_rate_limits_group_has_correct_limits(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/rate-limits');
        $groups = collect($response->json('data.groups'));

        $auth = $groups->firstWhere('group', 'auth');
        $this->assertEquals(5, $auth['limit_per_minute']);

        $api = $groups->firstWhere('group', 'api');
        $this->assertEquals(100, $api['limit_per_minute']);
    }
}
