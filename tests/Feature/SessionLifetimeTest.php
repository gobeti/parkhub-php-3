<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the absolute-session-lifetime cap enforced by
 * {@see EnforceAbsoluteSessionLifetime} and the
 * privilege-change session-regeneration rules (T-1744).
 */
class SessionLifetimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_request_within_cap_succeeds(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // auth_at stamped 1 hour ago — well within the 24h default cap.
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withSession(['auth_at' => now()->subHour()->timestamp])
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200);
    }

    public function test_first_authenticated_request_stamps_auth_at(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200);
        // Session was started and stamped — confirm the value exists and
        // is a reasonable unix timestamp (within the last minute).
        $this->assertIsInt(session('auth_at'));
        $this->assertGreaterThan(now()->subMinute()->timestamp, (int) session('auth_at'));
    }

    public function test_session_invalidated_after_absolute_cutoff(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // 25h ago — past the 1440-minute (24h) default cap.
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withSession(['auth_at' => now()->subHours(25)->timestamp])
            ->getJson('/api/v1/users/me');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }

    public function test_custom_absolute_lifetime_is_honoured(): void
    {
        // Shrink the cap to 60 minutes; a 2h-old session must be rejected.
        config(['session.absolute_lifetime' => 60]);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withSession(['auth_at' => now()->subHours(2)->timestamp])
            ->getJson('/api/v1/users/me');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }

    public function test_password_change_regenerates_session(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword123'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        // Seed auth_at so the middleware doesn't 401 us; grab the session ID
        // before the request so we can prove it rotates.
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withSession(['auth_at' => now()->subMinutes(5)->timestamp, '_token' => 'before'])
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'OldPassword123',
                'new_password' => 'NewPassword123',
                'new_password_confirmation' => 'NewPassword123',
            ]);

        $response->assertStatus(200);
        // Regeneration produces a fresh CSRF `_token` — the sentinel from
        // withSession() must have been overwritten.
        $this->assertNotSame('before', session('_token'));
    }

    public function test_unauthenticated_public_routes_unaffected(): void
    {
        // /api/v1/security/csp-report is unauthenticated by design; the
        // absolute-lifetime middleware must not apply.
        $response = $this->postJson('/api/v1/security/csp-report', []);

        // Any non-401 response proves the middleware didn't short-circuit;
        // the route accepts the beacon with a 204.
        $this->assertNotSame(401, $response->status());
    }

    public function test_unauthenticated_login_route_unaffected(): void
    {
        // /api/v1/auth/login is public and must not be gated by the cap.
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'nope',
            'password' => 'nope',
        ]);

        // A 401 *from the credentials check* is fine; what we need to
        // verify is that the middleware didn't invent its own
        // SESSION_EXPIRED response for an anonymous caller.
        $this->assertNotSame('SESSION_EXPIRED', $response->json('error.code'));
    }
}
