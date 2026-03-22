<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_info_endpoint(): void
    {
        $response = $this->getJson('/api/v1/health/info');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['version', 'php_version', 'laravel_version', 'environment', 'modules']]);
    }

    public function test_health_live_includes_uptime(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['status', 'uptime']]);
    }

    public function test_health_ready_checks_cache(): void
    {
        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['status', 'database', 'cache', 'version']]);
    }

    public function test_health_info_requires_no_auth(): void
    {
        $this->getJson('/api/v1/health/info')->assertStatus(200);
    }
}
