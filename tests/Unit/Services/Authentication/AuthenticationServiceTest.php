<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Authentication;

use App\Models\User;
use App\Services\Authentication\AuthenticationService;
use App\Services\Authentication\AuthOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_returns_user_and_token(): void
    {
        $user = User::factory()->create([
            'username' => 'alice',
            'password' => bcrypt('correct-horse'),
            'is_active' => true,
        ]);

        $service = app(AuthenticationService::class);

        $result = $service->attempt(
            ['username' => 'alice', 'password' => 'correct-horse'],
            ['ip' => '203.0.113.4', 'user_agent' => 'phpunit'],
        );

        $this->assertSame(AuthOutcome::Success, $result->outcome);
        $this->assertNotNull($result->user);
        $this->assertSame($user->id, $result->user->id);
        $this->assertNotNull($result->token);
        $this->assertNotEmpty($result->token->plainTextToken);
        $this->assertDatabaseHas('login_history', ['user_id' => $user->id, 'ip_address' => '203.0.113.4']);
    }

    public function test_wrong_password_returns_invalid_credentials(): void
    {
        User::factory()->create([
            'username' => 'bob',
            'password' => bcrypt('secret-pass'),
            'is_active' => true,
        ]);

        $service = app(AuthenticationService::class);

        $result = $service->attempt(
            ['username' => 'bob', 'password' => 'nope'],
            ['ip' => '198.51.100.1', 'user_agent' => 'phpunit'],
        );

        $this->assertSame(AuthOutcome::InvalidCredentials, $result->outcome);
        $this->assertNull($result->user);
        $this->assertNull($result->token);
    }

    public function test_disabled_account_returns_account_disabled(): void
    {
        User::factory()->create([
            'username' => 'ghost',
            'password' => bcrypt('still-valid'),
            'is_active' => false,
        ]);

        $service = app(AuthenticationService::class);

        $result = $service->attempt(
            ['username' => 'ghost', 'password' => 'still-valid'],
            ['ip' => null, 'user_agent' => null],
        );

        $this->assertSame(AuthOutcome::AccountDisabled, $result->outcome);
        $this->assertNull($result->token);
    }
}
