<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookV2ControllerTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        config(['modules.webhooks_v2' => true]);

        // Clean up webhook files
        $path = storage_path('app/webhooks_v2.json');
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function test_admin_can_list_webhooks(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/admin/webhooks-v2');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => []]);
    }

    public function test_admin_can_create_webhook(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'https://example.com/webhook',
                'events' => ['booking.created', 'lot.full'],
                'description' => 'Test webhook',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.url', 'https://example.com/webhook')
            ->assertJsonStructure(['data' => ['id', 'url', 'secret', 'events', 'active']]);
    }

    public function test_admin_can_get_single_webhook(): void
    {
        $token = $this->adminToken();

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'https://example.com/hook',
                'events' => ['booking.created'],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/admin/webhooks-v2/{$id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.url', 'https://example.com/hook');
    }

    public function test_admin_can_update_webhook(): void
    {
        $token = $this->adminToken();

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'https://example.com/hook',
                'events' => ['booking.created'],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/admin/webhooks-v2/{$id}", [
                'url' => 'https://example.com/updated',
                'events' => ['booking.created', 'payment.completed'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.url', 'https://example.com/updated');
    }

    public function test_admin_can_delete_webhook(): void
    {
        $token = $this->adminToken();

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'https://example.com/hook',
                'events' => ['booking.created'],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/admin/webhooks-v2/{$id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_delete_nonexistent_webhook_returns_404(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->deleteJson('/api/v1/admin/webhooks-v2/nonexistent');

        $response->assertStatus(404);
    }

    public function test_create_webhook_validates_url(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'not-a-url',
                'events' => ['booking.created'],
            ]);

        $response->assertStatus(422);
    }

    public function test_create_webhook_requires_events(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'https://example.com/hook',
            ]);

        $response->assertStatus(422);
    }

    public function test_webhook_has_hmac_secret(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'https://example.com/hook',
                'events' => ['booking.created'],
            ]);

        $response->assertStatus(201);
        $secret = $response->json('data.secret');
        $this->assertStringStartsWith('whsec_', $secret);
    }

    public function test_delivery_log_empty_initially(): void
    {
        $token = $this->adminToken();

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/webhooks-v2', [
                'url' => 'https://example.com/hook',
                'events' => ['booking.created'],
            ]);

        $id = $createResponse->json('data.id');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/admin/webhooks-v2/{$id}/deliveries");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => []]);
    }

    public function test_regular_user_cannot_access_webhooks(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->userToken())
            ->getJson('/api/v1/admin/webhooks-v2');

        $response->assertStatus(403);
    }
}
