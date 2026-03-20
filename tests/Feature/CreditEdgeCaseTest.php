<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_credits_zero_amount_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'credits_balance' => 5]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/'.$user->id.'/credits', ['amount' => 0])
            ->assertStatus(422);
    }

    public function test_grant_credits_negative_amount_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/'.$user->id.'/credits', ['amount' => -5])
            ->assertStatus(422);
    }

    public function test_grant_credits_exceeds_max_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/'.$user->id.'/credits', ['amount' => 1001])
            ->assertStatus(422);
    }

    public function test_grant_credits_boundary_max(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'credits_balance' => 0]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/'.$user->id.'/credits', ['amount' => 1000])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'credits_balance' => 1000]);
    }

    public function test_grant_credits_creates_transaction(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'credits_balance' => 0]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/'.$user->id.'/credits', [
                'amount' => 10,
                'description' => 'Test grant',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'amount' => 10,
            'type' => 'grant',
        ]);
    }

    public function test_grant_credits_to_nonexistent_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/'.$fakeId.'/credits', ['amount' => 5])
            ->assertStatus(404);
    }

    public function test_quota_update_creates_transaction(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'credits_monthly_quota' => 10]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$user->id.'/quota', ['monthly_quota' => 20])
            ->assertStatus(200);

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'type' => 'quota_adjustment',
        ]);
    }

    public function test_quota_boundary_zero(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'credits_monthly_quota' => 10]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$user->id.'/quota', ['monthly_quota' => 0])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'credits_monthly_quota' => 0]);
    }

    public function test_quota_boundary_max(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$user->id.'/quota', ['monthly_quota' => 999])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'credits_monthly_quota' => 999]);
    }

    public function test_quota_over_max_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$user->id.'/quota', ['monthly_quota' => 1000])
            ->assertStatus(422);
    }

    public function test_refill_all_credits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create(['role' => 'user', 'is_active' => true, 'credits_balance' => 2, 'credits_monthly_quota' => 10]);
        $user2 = User::factory()->create(['role' => 'user', 'is_active' => true, 'credits_balance' => 0, 'credits_monthly_quota' => 5]);
        $inactive = User::factory()->create(['role' => 'user', 'is_active' => false, 'credits_balance' => 0, 'credits_monthly_quota' => 10]);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/credits/refill-all');

        $response->assertStatus(200)
            ->assertJsonPath('data.users_refilled', 2);

        $this->assertDatabaseHas('users', ['id' => $user1->id, 'credits_balance' => 10]);
        $this->assertDatabaseHas('users', ['id' => $user2->id, 'credits_balance' => 5]);
        $this->assertDatabaseHas('users', ['id' => $inactive->id, 'credits_balance' => 0]);
    }

    public function test_credit_transactions_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $token = $admin->createToken('test')->plainTextToken;

        CreditTransaction::create([
            'user_id' => $user->id,
            'amount' => 10,
            'type' => 'grant',
            'description' => 'Test',
            'granted_by' => $admin->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/credits/transactions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_user_credits_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'user', 'credits_balance' => 42, 'credits_monthly_quota' => 50]);
        $token = $user->createToken('test')->plainTextToken;
        Setting::set('credits_enabled', 'true');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/credits');

        $response->assertStatus(200)
            ->assertJsonPath('data.balance', 42)
            ->assertJsonPath('data.monthly_quota', 50)
            ->assertJsonPath('data.enabled', true);
    }

    public function test_credits_disabled_still_returns_balance(): void
    {
        $user = User::factory()->create(['role' => 'user', 'credits_balance' => 10]);
        $token = $user->createToken('test')->plainTextToken;
        Setting::set('credits_enabled', 'false');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/credits');

        $response->assertStatus(200)
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.balance', 10);
    }
}
