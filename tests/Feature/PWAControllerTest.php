<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PWAControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['modules.enhanced_pwa' => true]);
    }

    private function userToken(): string
    {
        $user = User::factory()->create(['role' => 'user']);

        return $user->createToken('test')->plainTextToken;
    }

    public function test_manifest_returns_valid_pwa_manifest(): void
    {
        $response = $this->getJson('/api/v1/pwa/manifest');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'name',
                    'short_name',
                    'description',
                    'start_url',
                    'display',
                    'theme_color',
                    'background_color',
                    'icons',
                    'shortcuts',
                ],
            ]);
    }

    public function test_manifest_has_correct_display_mode(): void
    {
        $response = $this->getJson('/api/v1/pwa/manifest');

        $response->assertStatus(200)
            ->assertJsonPath('data.display', 'standalone');
    }

    public function test_manifest_has_icons(): void
    {
        $response = $this->getJson('/api/v1/pwa/manifest');

        $response->assertStatus(200);
        $icons = $response->json('data.icons');
        $this->assertCount(5, $icons);
    }

    public function test_offline_data_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/pwa/offline-data');

        $response->assertStatus(401);
    }

    public function test_offline_data_returns_structure(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->userToken())
            ->getJson('/api/v1/pwa/offline-data');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'next_booking',
                    'lot_info',
                    'cached_at',
                ],
            ]);
    }

    public function test_offline_data_has_null_booking_when_none_exist(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->userToken())
            ->getJson('/api/v1/pwa/offline-data');

        $response->assertStatus(200)
            ->assertJsonPath('data.next_booking', null);
    }
}
