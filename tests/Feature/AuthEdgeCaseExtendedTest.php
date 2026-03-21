<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthEdgeCaseExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPass1'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'WrongPass1',
        ])->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_login_with_nonexistent_user(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'username' => 'ghost_user',
            'password' => 'AnyPass123',
        ])->assertStatus(401);
    }

    public function test_register_password_mismatch_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => 'newuser99',
            'email' => 'newuser99@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'DifferentPass123',
            'name' => 'Test User',
        ])->assertStatus(422);
    }

    public function test_register_creates_user_with_correct_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'freshuser',
            'email' => 'freshuser@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'name' => 'Fresh User',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'username' => 'freshuser',
            'role' => 'user',
        ]);
    }

    public function test_register_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'tokenuser',
            'email' => 'tokenuser@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'name' => 'Token User',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['tokens' => ['access_token']]]);
    }

    public function test_refresh_returns_new_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Refresh should succeed and return a new access_token
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['tokens' => ['access_token']]]);

        $newToken = $response->json('data.tokens.access_token');
        $this->assertNotEquals($token, $newToken);

        // Old tokens are deleted; after refresh only 1 token should remain
        $this->assertEquals(1, $user->tokens()->count());
    }

    public function test_unauthenticated_refresh_rejected(): void
    {
        $this->postJson('/api/v1/auth/refresh')
            ->assertStatus(401);
    }

    public function test_unauthenticated_me_rejected(): void
    {
        $this->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    public function test_delete_account_success(): void
    {
        $user = User::factory()->create(['password' => Hash::make('DeleteMe1')]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/users/me/delete', ['password' => 'DeleteMe1'])
            ->assertStatus(200);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_change_password_new_password_weak_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'OldPassword1',
                'new_password' => 'weak',
            ])
            ->assertStatus(422);
    }

    public function test_change_password_returns_new_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'OldPassword1',
                'new_password' => 'NewPassword1',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['tokens' => ['access_token']]]);
    }

    public function test_reset_password_with_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        // Insert a real reset token
        DB::table('password_reset_tokens')->insert([
            'email' => 'reset@example.com',
            'token' => Hash::make('valid-token'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => 'invalid-token',
            'password' => 'NewSecure1',
            'password_confirmation' => 'NewSecure1',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_TOKEN');
    }

    public function test_reset_password_with_expired_token(): void
    {
        User::factory()->create(['email' => 'expired@example.com']);

        // Insert an expired reset token (2 hours ago, beyond the 60-minute window)
        DB::table('password_reset_tokens')->insert([
            'email' => 'expired@example.com',
            'token' => Hash::make('expired-token'),
            'created_at' => now()->subHours(2),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'expired@example.com',
            'token' => 'expired-token',
            'password' => 'NewSecure1',
            'password_confirmation' => 'NewSecure1',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_TOKEN');
    }

    public function test_forgot_password_with_existing_email(): void
    {
        User::factory()->create(['email' => 'exists@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'exists@example.com',
        ])->assertStatus(200);

        // A reset token should have been created
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'exists@example.com',
        ]);
    }

    public function test_update_me_phone(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/me', ['phone' => '+49 123 456789'])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '+49 123 456789']);
    }

    public function test_update_me_department(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/me', ['department' => 'R&D'])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'department' => 'R&D']);
    }
}
