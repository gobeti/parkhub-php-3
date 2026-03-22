<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_active_sessions(): void
    {
        $user = User::factory()->create();
        $user->createToken('device-1');
        $user->createToken('device-2');
        $currentToken = $user->createToken('current')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$currentToken)
            ->getJson('/api/v1/auth/sessions');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_session_marks_current(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$currentToken)
            ->getJson('/api/v1/auth/sessions');

        $response->assertStatus(200);
        $sessions = $response->json('data');
        $currentSessions = array_filter($sessions, fn ($s) => $s['is_current'] === true);
        $this->assertCount(1, $currentSessions);
    }

    public function test_revoke_specific_session(): void
    {
        $user = User::factory()->create();
        $otherToken = $user->createToken('other');
        $currentToken = $user->createToken('current')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$currentToken)
            ->deleteJson("/api/v1/auth/sessions/{$otherToken->accessToken->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $user->tokens);
    }

    public function test_cannot_revoke_current_session(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('current');
        $plainToken = $token->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->deleteJson("/api/v1/auth/sessions/{$token->accessToken->id}");

        $response->assertStatus(400);
    }

    public function test_revoke_all_except_current(): void
    {
        $user = User::factory()->create();
        $user->createToken('old-1');
        $user->createToken('old-2');
        $user->createToken('old-3');
        $currentToken = $user->createToken('current')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$currentToken)
            ->deleteJson('/api/v1/auth/sessions');

        $response->assertStatus(200)
            ->assertJsonPath('data.revoked_count', 3);

        $this->assertCount(1, $user->fresh()->tokens);
    }

    public function test_revoke_nonexistent_session(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth/sessions/99999');

        $response->assertStatus(404);
    }
}
