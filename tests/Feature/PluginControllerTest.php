<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return $admin->createToken('test')->plainTextToken;
    }

    private function userToken(): string
    {
        $user = User::factory()->create(['role' => 'user']);

        return $user->createToken('test')->plainTextToken;
    }

    public function test_admin_can_list_plugins(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/plugins');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'plugins' => [
                        '*' => ['id', 'name', 'description', 'version', 'author', 'enabled', 'hooks', 'config'],
                    ],
                    'total',
                    'enabled',
                    'available_hooks',
                ],
            ])
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.plugins.0.id', 'slack-notifier')
            ->assertJsonPath('data.plugins.1.id', 'auto-assign-preferred');
    }

    public function test_regular_user_cannot_list_plugins(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->userToken())
            ->getJson('/api/v1/admin/plugins');

        $response->assertStatus(403);
    }

    public function test_admin_can_toggle_plugin(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->putJson('/api/v1/admin/plugins/slack-notifier/toggle');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['id', 'enabled', 'message']])
            ->assertJsonPath('data.id', 'slack-notifier');
    }

    public function test_toggle_nonexistent_plugin_returns_404(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->putJson('/api/v1/admin/plugins/nonexistent/toggle');

        $response->assertStatus(404)
            ->assertJsonPath('error', 'PLUGIN_NOT_FOUND');
    }

    public function test_admin_can_get_plugin_config(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/plugins/slack-notifier/config');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['id', 'name', 'config', 'hooks']])
            ->assertJsonPath('data.id', 'slack-notifier')
            ->assertJsonPath('data.name', 'Slack Notifier');
    }

    public function test_get_config_nonexistent_plugin_returns_404(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/plugins/ghost/config');

        $response->assertStatus(404);
    }

    public function test_admin_can_update_plugin_config(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->putJson('/api/v1/admin/plugins/slack-notifier/config', [
                'config' => ['webhook_url' => 'https://hooks.slack.com/test', 'channel' => '#alerts'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.config.webhook_url', 'https://hooks.slack.com/test')
            ->assertJsonPath('data.config.channel', '#alerts');
    }

    public function test_update_config_with_invalid_payload(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->putJson('/api/v1/admin/plugins/slack-notifier/config', [
                'config' => 'not-an-object',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_CONFIG');
    }

    public function test_available_hooks_listed(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/plugins');

        $response->assertStatus(200);

        $hooks = $response->json('data.available_hooks');
        $this->assertContains('booking_created', $hooks);
        $this->assertContains('booking_cancelled', $hooks);
        $this->assertContains('user_registered', $hooks);
        $this->assertContains('lot_full', $hooks);
    }
}
