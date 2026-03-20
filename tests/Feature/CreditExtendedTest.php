<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_with_exact_credits_succeeds(): void
    {
        $user = User::factory()->create(['role' => 'user', 'credits_balance' => 1]);
        $token = $user->createToken('test')->plainTextToken;

        Setting::set('credits_enabled', 'true');
        Setting::set('credits_per_booking', '1');

        $lot = ParkingLot::create(['name' => 'Exact Credit Lot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'EC1', 'status' => 'available']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'start_time' => now()->addHour()->toDateTimeString(),
                'end_time' => now()->addHours(3)->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'credits_balance' => 0]);
    }

    public function test_admin_bypasses_credit_check(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'credits_balance' => 0]);
        $token = $admin->createToken('test')->plainTextToken;

        Setting::set('credits_enabled', 'true');
        Setting::set('credits_per_booking', '5');

        $lot = ParkingLot::create(['name' => 'Admin Credit Lot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'AD1', 'status' => 'available']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'start_time' => now()->addHour()->toDateTimeString(),
                'end_time' => now()->addHours(3)->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        // Admin balance should remain unchanged
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'credits_balance' => 0]);
    }

    public function test_credits_disabled_allows_booking_without_credits(): void
    {
        $user = User::factory()->create(['role' => 'user', 'credits_balance' => 0]);
        $token = $user->createToken('test')->plainTextToken;

        Setting::set('credits_enabled', 'false');

        $lot = ParkingLot::create(['name' => 'No Credit Lot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'NC1', 'status' => 'available']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'start_time' => now()->addHour()->toDateTimeString(),
                'end_time' => now()->addHours(3)->toDateTimeString(),
            ]);

        $response->assertStatus(201);
    }

    public function test_refill_all_with_custom_amount(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create(['role' => 'user', 'is_active' => true, 'credits_balance' => 3, 'credits_monthly_quota' => 10]);
        $user2 = User::factory()->create(['role' => 'user', 'is_active' => true, 'credits_balance' => 7, 'credits_monthly_quota' => 20]);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/credits/refill-all', ['amount' => 15]);

        $response->assertStatus(200)
            ->assertJsonPath('data.users_refilled', 2);

        // Both users should get exactly 15, regardless of their quota
        $this->assertDatabaseHas('users', ['id' => $user1->id, 'credits_balance' => 15]);
        $this->assertDatabaseHas('users', ['id' => $user2->id, 'credits_balance' => 15]);
    }

    public function test_refill_creates_monthly_refill_transactions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'is_active' => true, 'credits_balance' => 0, 'credits_monthly_quota' => 10]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/credits/refill-all')
            ->assertStatus(200);

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'type' => 'monthly_refill',
        ]);
    }

    public function test_non_admin_cannot_grant_credits(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $target = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/'.$target->id.'/credits', ['amount' => 10])
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_refill_all(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/credits/refill-all')
            ->assertStatus(403);
    }

    public function test_credit_transactions_filtered_by_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);
        $token = $admin->createToken('test')->plainTextToken;

        CreditTransaction::create([
            'user_id' => $user1->id, 'amount' => 10, 'type' => 'grant',
            'description' => 'Grant 1', 'granted_by' => $admin->id,
        ]);
        CreditTransaction::create([
            'user_id' => $user2->id, 'amount' => 5, 'type' => 'grant',
            'description' => 'Grant 2', 'granted_by' => $admin->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/credits/transactions?user_id='.$user1->id);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($user1->id, $data[0]['user_id']);
    }

    public function test_credit_transactions_filtered_by_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $token = $admin->createToken('test')->plainTextToken;

        CreditTransaction::create([
            'user_id' => $user->id, 'amount' => 10, 'type' => 'grant',
            'description' => 'Grant', 'granted_by' => $admin->id,
        ]);
        CreditTransaction::create([
            'user_id' => $user->id, 'amount' => -2, 'type' => 'deduction',
            'description' => 'Deduction',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/credits/transactions?type=grant');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('grant', $data[0]['type']);
    }

    public function test_quota_negative_value_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$user->id.'/quota', ['monthly_quota' => -1])
            ->assertStatus(422);
    }

    public function test_grant_credits_non_integer_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/'.$user->id.'/credits', ['amount' => 'five'])
            ->assertStatus(422);
    }
}
