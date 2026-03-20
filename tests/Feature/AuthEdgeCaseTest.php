<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_email_instead_of_username(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('SecurePass123'),
        ]);

        $this->postJson('/api/login', [
            'username' => 'test@example.com',
            'password' => 'SecurePass123',
        ])->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['user', 'tokens']]);
    }

    public function test_login_disabled_account(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('SecurePass123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'SecurePass123',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_DISABLED');
    }

    public function test_register_duplicate_username(): void
    {
        User::factory()->create(['username' => 'taken']);

        $this->postJson('/api/register', [
            'username' => 'taken',
            'email' => 'new@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_register_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->postJson('/api/register', [
            'username' => 'unique_user',
            'email' => 'existing@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_register_weak_password_rejected(): void
    {
        $this->postJson('/api/register', [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_register_password_no_uppercase_rejected(): void
    {
        $this->postJson('/api/register', [
            'username' => 'newuser2',
            'email' => 'newuser2@example.com',
            'password' => 'alllowercase1',
            'password_confirmation' => 'alllowercase1',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_register_password_no_digit_rejected(): void
    {
        $this->postJson('/api/register', [
            'username' => 'newuser3',
            'email' => 'newuser3@example.com',
            'password' => 'NoDigitsHere',
            'password_confirmation' => 'NoDigitsHere',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_register_when_disabled(): void
    {
        Setting::set('self_registration', 'false');

        $this->postJson('/api/register', [
            'username' => 'noway',
            'email' => 'noway@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'name' => 'No Way',
        ])->assertStatus(403);
    }

    public function test_change_password_wrong_current(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'WrongCurrent1',
                'new_password' => 'NewPassword1',
            ])
            ->assertStatus(400);
    }

    public function test_change_password_success(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'OldPassword1',
                'new_password' => 'NewPassword1',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['tokens']]);
    }

    public function test_update_me_email_unique(): void
    {
        $user1 = User::factory()->create(['email' => 'first@example.com']);
        $user2 = User::factory()->create(['email' => 'second@example.com']);
        $token = $user2->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/me', ['email' => 'first@example.com'])
            ->assertStatus(422);
    }

    public function test_update_me_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/me', ['name' => 'New Name'])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_me_endpoint(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', $user->username);
    }

    public function test_refresh_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['tokens' => ['access_token']]]);
    }

    public function test_forgot_password_returns_generic_response(): void
    {
        // Even with non-existent email, should return 200 (prevent enumeration)
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ])->assertStatus(200);
    }

    public function test_register_username_too_short(): void
    {
        $this->postJson('/api/register', [
            'username' => 'ab',
            'email' => 'short@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_login_missing_fields(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422);
    }

    public function test_delete_account_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Correct1')]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/users/me/delete', ['password' => 'Wrong1'])
            ->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
