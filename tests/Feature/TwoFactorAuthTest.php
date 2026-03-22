<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_setup_2fa_returns_secret_and_qr_uri(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/2fa/setup');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['secret', 'qr_uri']]);

        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_setup_2fa_fails_if_already_enabled(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_secret' => 'TESTSECRET123456',
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/2fa/setup');

        $response->assertStatus(400);
    }

    public function test_verify_2fa_enables_with_valid_code(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $user = User::factory()->create(['two_factor_secret' => $secret]);

        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/2fa/verify', ['code' => $code]);

        $response->assertStatus(200);
        $this->assertTrue($user->fresh()->two_factor_enabled);
    }

    public function test_verify_2fa_fails_with_invalid_code(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $user = User::factory()->create(['two_factor_secret' => $secret]);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/2fa/verify', ['code' => '000000']);

        $response->assertStatus(422);
        $this->assertFalse($user->fresh()->two_factor_enabled);
    }

    public function test_verify_2fa_fails_without_setup(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/2fa/verify', ['code' => '123456']);

        $response->assertStatus(400);
    }

    public function test_disable_2fa_with_correct_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123'),
            'two_factor_enabled' => true,
            'two_factor_secret' => 'TESTSECRET123456',
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/2fa/disable', ['password' => 'Password123']);

        $response->assertStatus(200);
        $this->assertFalse($user->fresh()->two_factor_enabled);
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_disable_2fa_fails_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123'),
            'two_factor_enabled' => true,
            'two_factor_secret' => 'TESTSECRET123456',
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/2fa/disable', ['password' => 'WrongPass']);

        $response->assertStatus(403);
        $this->assertTrue($user->fresh()->two_factor_enabled);
    }

    public function test_disable_2fa_fails_when_not_enabled(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123')]);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/auth/2fa/disable', ['password' => 'Password123']);

        $response->assertStatus(400);
    }

    public function test_login_requires_2fa_code_when_enabled(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123'),
            'two_factor_enabled' => true,
            'two_factor_secret' => 'TESTSECRET123456',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.requires_2fa', true);
    }

    public function test_login_succeeds_with_valid_2fa_code(): void
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
            ->assertJsonStructure(['data' => ['user', 'tokens']]);
    }

    public function test_login_fails_with_invalid_2fa_code(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $user = User::factory()->create([
            'password' => bcrypt('Password123'),
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'Password123',
            'two_factor_code' => '000000',
        ]);

        $response->assertStatus(401);
    }
}
