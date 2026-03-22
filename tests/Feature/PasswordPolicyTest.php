<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_rejects_short_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'Ab1',
            'password_confirmation' => 'Ab1',
            'name' => 'Test',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_rejects_password_without_uppercase(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_rejects_password_without_number(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'PasswordNoNum',
            'password_confirmation' => 'PasswordNoNum',
            'name' => 'Test',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_accepts_valid_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'ValidPass123',
            'password_confirmation' => 'ValidPass123',
            'name' => 'Test User',
        ]);

        $response->assertStatus(201);
    }

    public function test_change_password_enforces_policy(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPass123')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'OldPass123',
                'new_password' => 'weak',
            ]);

        $response->assertStatus(422);
    }

    public function test_register_rejects_password_without_lowercase(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'PASSWORD123',
            'password_confirmation' => 'PASSWORD123',
            'name' => 'Test',
        ]);

        $response->assertStatus(422);
    }
}
