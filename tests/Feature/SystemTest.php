<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_endpoint_returns_200(): void
    {
        $response = $this->getJson('/api/v1/system/version');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data' => ['version', 'build']]);
    }

    public function test_version_endpoint_returns_version_string(): void
    {
        $response = $this->getJson('/api/v1/system/version');

        $response->assertStatus(200)
            ->assertJsonPath('data.build', 'php-laravel');
    }

    public function test_maintenance_endpoint_returns_200(): void
    {
        $response = $this->getJson('/api/v1/system/maintenance');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['active', 'message']]);
    }

    public function test_maintenance_defaults_to_inactive(): void
    {
        $response = $this->getJson('/api/v1/system/maintenance');

        $response->assertStatus(200)
            ->assertJsonPath('data.active', false);
    }

    public function test_maintenance_active_when_setting_enabled(): void
    {
        Setting::set('maintenance_mode', 'true');
        Setting::set('maintenance_message', 'Scheduled downtime');

        $response = $this->getJson('/api/v1/system/maintenance');

        $response->assertStatus(200)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.message', 'Scheduled downtime');
    }

    public function test_version_does_not_require_auth(): void
    {
        $response = $this->getJson('/api/v1/system/version');
        $response->assertStatus(200);
    }

    public function test_maintenance_does_not_require_auth(): void
    {
        $response = $this->getJson('/api/v1/system/maintenance');
        $response->assertStatus(200);
    }

    public function test_maintenance_message_empty_by_default(): void
    {
        $response = $this->getJson('/api/v1/system/maintenance');

        $response->assertStatus(200)
            ->assertJsonPath('data.message', '');
    }
}
