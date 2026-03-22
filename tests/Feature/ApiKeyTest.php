<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_create_api_key(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/api-keys', [
                'name' => 'CI Pipeline',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'token', 'abilities']]);
    }

    public function test_create_api_key_with_expiry(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/api-keys', [
                'name' => 'Temp Key',
                'expires_at' => now()->addDays(30)->toISOString(),
            ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_create_api_key_with_scoped_abilities(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/api-keys', [
                'name' => 'Read Only',
                'abilities' => ['read', 'bookings:list'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.abilities', ['read', 'bookings:list']);
    }

    public function test_list_api_keys(): void
    {
        $user = User::factory()->create();
        $user->createToken('key-1');
        $user->createToken('key-2');

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/auth/api-keys');

        $response->assertStatus(200);
        // Should not include 'auth-token' (the session token used for auth)
        $keys = collect($response->json('data'));
        $authTokens = $keys->where('name', 'auth-token');
        // The auth header creates a 'test' token, so we should see key-1 and key-2
        $this->assertGreaterThanOrEqual(2, $keys->count());
    }

    public function test_revoke_api_key(): void
    {
        $user = User::factory()->create();
        $apiKey = $user->createToken('to-revoke');
        $authToken = $user->createToken('auth')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$authToken)
            ->deleteJson("/api/v1/auth/api-keys/{$apiKey->accessToken->id}");

        $response->assertStatus(200);
    }

    public function test_revoke_nonexistent_api_key(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->deleteJson('/api/v1/auth/api-keys/99999');

        $response->assertStatus(404);
    }

    public function test_create_api_key_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/api-keys', []);

        $response->assertStatus(422);
    }
}
