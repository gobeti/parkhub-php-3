<?php

namespace Tests\Feature;

use App\Jobs\AggregateSystemMetricsJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PulseTest extends TestCase
{
    use RefreshDatabase;

    public function test_pulse_requires_authentication(): void
    {
        $this->getJson('/api/admin/pulse')
            ->assertStatus(401);
    }

    public function test_pulse_requires_admin_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/pulse')
            ->assertStatus(403);
    }

    public function test_pulse_returns_metrics_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/pulse');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'requests_last_hour',
                'requests_last_day',
                'slow_queries',
                'failed_jobs_total',
                'failed_jobs_last_hour',
                'cache_hit_rate',
                'active_users',
                'total_users',
                'queue_depth',
                'bookings_today',
                'active_bookings',
                'php_memory_usage_mb',
                'php_memory_peak_mb',
                'collected_at',
            ]]);
    }

    public function test_pulse_returns_cached_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $fakeMetrics = [
            'requests_last_hour' => 42,
            'requests_last_day' => 1337,
            'slow_queries' => 3,
            'failed_jobs_total' => 1,
            'failed_jobs_last_hour' => 0,
            'cache_hit_rate' => 95.5,
            'cache_hits' => 191,
            'cache_misses' => 9,
            'active_users' => 5,
            'total_users' => 20,
            'queue_depth' => 2,
            'queue_depth_default' => 2,
            'bookings_today' => 15,
            'active_bookings' => 8,
            'memory' => null,
            'cpu' => null,
            'php_memory_usage_mb' => 32.0,
            'php_memory_peak_mb' => 48.0,
            'database' => ['driver' => 'sqlite', 'size_mb' => 0.5, 'path' => '/tmp/test.sqlite'],
            'uptime_seconds' => 86400,
            'collected_at' => now()->toIso8601String(),
        ];
        Cache::put('system_metrics', $fakeMetrics, now()->addMinutes(10));

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/pulse');

        $response->assertStatus(200)
            ->assertJsonPath('data.requests_last_hour', 42)
            ->assertJsonPath('data.active_users', 5)
            ->assertJsonPath('data.total_users', 20);
    }

    public function test_pulse_v1_route_works(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/pulse');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'requests_last_hour',
                'active_users',
                'queue_depth',
                'collected_at',
            ]]);
    }

    public function test_aggregate_system_metrics_job_populates_cache(): void
    {
        Cache::forget('system_metrics');

        $job = new AggregateSystemMetricsJob;
        $job->handle();

        $metrics = Cache::get('system_metrics');
        $this->assertNotNull($metrics);
        $this->assertArrayHasKey('requests_last_hour', $metrics);
        $this->assertArrayHasKey('failed_jobs_total', $metrics);
        $this->assertArrayHasKey('active_users', $metrics);
        $this->assertArrayHasKey('queue_depth', $metrics);
        $this->assertArrayHasKey('bookings_today', $metrics);
        $this->assertArrayHasKey('php_memory_usage_mb', $metrics);
        $this->assertArrayHasKey('collected_at', $metrics);
        $this->assertIsInt($metrics['requests_last_hour']);
        $this->assertIsInt($metrics['failed_jobs_total']);
        $this->assertIsInt($metrics['total_users']);
    }

    public function test_superadmin_can_access_pulse(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $token = $superadmin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/pulse')
            ->assertStatus(200);
    }
}
