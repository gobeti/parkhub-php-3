<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiVersionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_endpoint_returns_version_info(): void
    {
        $response = $this->getJson('/api/v1/version');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['version', 'api_prefix', 'status', 'deprecations', 'supported_versions'],
            ])
            ->assertJsonPath('data.api_prefix', '/api/v1')
            ->assertJsonPath('data.status', 'stable');
    }

    public function test_version_endpoint_includes_deprecations(): void
    {
        $response = $this->getJson('/api/v1/version');

        $response->assertStatus(200);

        $deprecations = $response->json('data.deprecations');
        $this->assertIsArray($deprecations);
        $this->assertGreaterThan(0, count($deprecations));
        $this->assertArrayHasKey('endpoint', $deprecations[0]);
        $this->assertArrayHasKey('method', $deprecations[0]);
        $this->assertArrayHasKey('severity', $deprecations[0]);
    }

    public function test_changelog_endpoint_returns_entries(): void
    {
        $response = $this->getJson('/api/v1/changelog');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['entries', 'total'],
            ]);

        $total = $response->json('data.total');
        $this->assertGreaterThan(0, $total);
    }

    public function test_api_version_header_is_present(): void
    {
        $response = $this->getJson('/api/v1/version');

        $response->assertHeader('X-API-Version', '1');
    }
}
