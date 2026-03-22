<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_records_history(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123')]);

        $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'Password123',
        ]);

        $this->assertDatabaseHas('login_history', ['user_id' => $user->id]);
    }

    public function test_user_can_view_login_history(): void
    {
        $user = User::factory()->create();
        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'logged_in_at' => now(),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/login-history');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_login_history_limited_to_20(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 25; $i++) {
            LoginHistory::create([
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test',
                'logged_in_at' => now()->subMinutes($i),
            ]);
        }

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/login-history');

        $response->assertStatus(200)
            ->assertJsonCount(20, 'data');
    }

    public function test_admin_can_view_user_login_history(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Firefox',
            'logged_in_at' => now(),
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/admin/users/{$user->id}/login-history");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_login_history_requires_auth(): void
    {
        $this->getJson('/api/v1/auth/login-history')->assertStatus(401);
    }
}
