<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityExtendedTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    // ── 2FA Extended ────────────────────────────────────────────────────

    public function test_2fa_setup_requires_auth(): void
    {
        $this->postJson('/api/v1/auth/2fa/setup')
            ->assertStatus(401);
    }

    public function test_2fa_verify_requires_auth(): void
    {
        $this->postJson('/api/v1/auth/2fa/verify', ['code' => '123456'])
            ->assertStatus(401);
    }

    public function test_2fa_disable_requires_auth(): void
    {
        $this->postJson('/api/v1/auth/2fa/disable', ['password' => 'test'])
            ->assertStatus(401);
    }

    public function test_2fa_verify_requires_code_field(): void
    {
        $user = User::factory()->create(['two_factor_secret' => 'TESTSECRET123456']);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/2fa/verify', []);

        $response->assertStatus(422);
    }

    public function test_2fa_disable_requires_password_field(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_secret' => 'TESTSECRET123456',
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/2fa/disable', []);

        $response->assertStatus(422);
    }

    public function test_2fa_setup_generates_unique_secrets(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->withHeaders($this->authHeader($user1))
            ->postJson('/api/v1/auth/2fa/setup');

        $this->withHeaders($this->authHeader($user2))
            ->postJson('/api/v1/auth/2fa/setup');

        $this->assertNotEquals(
            $user1->fresh()->two_factor_secret,
            $user2->fresh()->two_factor_secret
        );
    }

    public function test_login_with_2fa_returns_tokens_on_success(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $user = User::factory()->create([
            'password' => bcrypt('Password123'),
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);

        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'Password123',
            'two_factor_code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['tokens' => ['access_token', 'token_type']]]);
    }

    // ── Login History Extended ───────────────────────────────────────────

    public function test_login_history_records_ip_address(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123')]);

        $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'Password123',
        ]);

        $history = LoginHistory::where('user_id', $user->id)->first();
        $this->assertNotNull($history);
        $this->assertNotNull($history->ip_address);
    }

    public function test_login_history_records_user_agent(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123')]);

        $this->withHeaders(['User-Agent' => 'TestBrowser/1.0'])
            ->postJson('/api/v1/auth/login', [
                'username' => $user->username,
                'password' => 'Password123',
            ]);

        $history = LoginHistory::where('user_id', $user->id)->first();
        $this->assertNotNull($history);
        $this->assertStringContainsString('TestBrowser', $history->user_agent);
    }

    public function test_failed_login_does_not_record_history(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123')]);

        $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'WrongPassword',
        ]);

        $this->assertDatabaseMissing('login_history', ['user_id' => $user->id]);
    }

    public function test_login_history_ordered_newest_first(): void
    {
        $user = User::factory()->create();
        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Old',
            'logged_in_at' => now()->subHour(),
        ]);
        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => '10.0.0.2',
            'user_agent' => 'New',
            'logged_in_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/auth/login-history');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('10.0.0.2', $data[0]['ip_address']);
    }

    // ── Session Management Extended ─────────────────────────────────────

    public function test_sessions_require_auth(): void
    {
        $this->getJson('/api/v1/auth/sessions')->assertStatus(401);
    }

    public function test_session_delete_requires_auth(): void
    {
        $this->deleteJson('/api/v1/auth/sessions/1')->assertStatus(401);
    }

    public function test_session_delete_all_requires_auth(): void
    {
        $this->deleteJson('/api/v1/auth/sessions')->assertStatus(401);
    }

    public function test_sessions_list_includes_token_names(): void
    {
        $user = User::factory()->create();
        $user->createToken('mobile-app');
        $currentToken = $user->createToken('web-browser')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$currentToken)
            ->getJson('/api/v1/auth/sessions');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('mobile-app', $names);
        $this->assertContains('web-browser', $names);
    }

    public function test_revoke_all_returns_correct_count(): void
    {
        $user = User::factory()->create();
        $user->createToken('old-1');
        $user->createToken('old-2');
        $currentToken = $user->createToken('current')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$currentToken)
            ->deleteJson('/api/v1/auth/sessions');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.revoked_count'));
    }

    // ── API Key Extended ────────────────────────────────────────────────

    public function test_api_key_create_requires_auth(): void
    {
        $this->postJson('/api/v1/auth/api-keys', ['name' => 'test'])
            ->assertStatus(401);
    }

    public function test_api_key_list_requires_auth(): void
    {
        $this->getJson('/api/v1/auth/api-keys')->assertStatus(401);
    }

    public function test_api_key_delete_requires_auth(): void
    {
        $this->deleteJson('/api/v1/auth/api-keys/1')->assertStatus(401);
    }

    public function test_api_key_name_max_length(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/api-keys', [
                'name' => str_repeat('X', 256),
            ]);

        // Should either work or reject — but not crash
        $this->assertContains($response->status(), [201, 422]);
    }

    public function test_api_key_with_past_expiry_date(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/api-keys', [
                'name' => 'Expired Key',
                'expires_at' => now()->subDay()->toISOString(),
            ]);

        // Accept or reject — either is fine, just shouldn't crash
        $this->assertContains($response->status(), [201, 422]);
    }

    public function test_cannot_revoke_other_users_api_key(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $key = $user1->createToken('owned-by-user1');

        $response = $this->withHeaders($this->authHeader($user2))
            ->deleteJson("/api/v1/auth/api-keys/{$key->accessToken->id}");

        $response->assertStatus(404);
    }

    // ── Password Policy Extended ────────────────────────────────────────

    public function test_password_max_128_chars_rejected(): void
    {
        $longPass = str_repeat('Aa1', 50); // 150 chars

        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'longpassuser',
            'email' => 'longpass@example.com',
            'password' => $longPass,
            'password_confirmation' => $longPass,
            'name' => 'Long Pass',
        ]);

        $response->assertStatus(422);
    }

    public function test_password_with_special_chars_accepted(): void
    {
        config(['parkhub.password_require_special' => true]);

        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'specialuser',
            'email' => 'special@example.com',
            'password' => 'ValidP@ss1!',
            'password_confirmation' => 'ValidP@ss1!',
            'name' => 'Special User',
        ]);

        $response->assertStatus(201);
    }

    public function test_change_password_with_wrong_current_returns_400(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPass123')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'WrongOldPass1',
                'new_password' => 'NewValidPass1',
            ]);

        $response->assertStatus(400);
    }

    public function test_change_password_deletes_old_tokens(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPass123')]);
        $user->createToken('old-session');
        $user->createToken('another-old');
        $authToken = $user->createToken('test')->plainTextToken;

        // Should have 3 tokens before password change
        $this->assertEquals(3, $user->tokens()->count());

        $this->withHeader('Authorization', 'Bearer '.$authToken)
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'OldPass123',
                'new_password' => 'NewValidPass1',
            ]);

        // Old tokens deleted, new one created = 1 token
        $this->assertEquals(1, $user->fresh()->tokens()->count());
    }

    public function test_change_password_returns_new_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPass123')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'OldPass123',
                'new_password' => 'NewValidPass1',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['tokens' => ['access_token']]]);
    }

    // ── Auth Edge Cases ─────────────────────────────────────────────────

    public function test_login_with_email_instead_of_username(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('Password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'login@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200);
    }

    public function test_login_disabled_account_returns_403(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'Password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_login_wrong_password_returns_401(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'WrongPass999',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_nonexistent_user_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'nonexistent_user',
            'password' => 'SomePass123',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_updates_last_login_timestamp(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123'),
            'last_login' => null,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'Password123',
        ]);

        $this->assertNotNull($user->fresh()->last_login);
    }

    public function test_register_returns_user_data_and_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'newuser123',
            'email' => 'newuser123@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'New User',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['user', 'tokens' => ['access_token']],
            ]);
    }

    public function test_register_creates_user_with_correct_role(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => 'roletest',
            'email' => 'roletest@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'Role Test',
        ]);

        $this->assertDatabaseHas('users', ['username' => 'roletest', 'role' => 'user']);
    }

    public function test_register_disabled_returns_403(): void
    {
        Setting::set('self_registration', 'false');

        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'blocked',
            'email' => 'blocked@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'Blocked',
        ]);

        $response->assertStatus(403);
    }

    public function test_me_endpoint_returns_user_profile(): void
    {
        $user = User::factory()->create(['name' => 'Profile Test']);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Profile Test');
    }

    public function test_me_endpoint_requires_auth(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_update_me_changes_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/me', ['name' => 'New Name']);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $user->fresh()->name);
    }

    public function test_update_me_validates_email_uniqueness(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/me', ['email' => 'taken@example.com']);

        $response->assertStatus(422);
    }

    public function test_refresh_returns_new_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['tokens' => ['access_token']]]);
    }
}
