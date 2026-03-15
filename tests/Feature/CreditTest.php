<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_user_quota(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'credits_monthly_quota' => 10]);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$user->id.'/quota', [
                'monthly_quota' => 25,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits_monthly_quota' => 25,
        ]);
    }

    public function test_non_admin_cannot_update_quota(): void
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        $targetUser = User::factory()->create(['role' => 'user']);
        $token = $regularUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/users/'.$targetUser->id.'/quota', [
                'monthly_quota' => 50,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_grant_credits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'credits_balance' => 5]);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/users/'.$user->id.'/credits', [
                'amount' => 10,
                'description' => 'Test grant',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.credits_balance', 15);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits_balance' => 15,
        ]);
    }

    public function test_credit_deduction_on_booking(): void
    {
        $user = User::factory()->create(['role' => 'user', 'credits_balance' => 10]);
        $token = $user->createToken('test')->plainTextToken;

        // Enable credits system
        Setting::set('credits_enabled', 'true');
        Setting::set('credits_per_booking', '2');

        $lot = ParkingLot::create([
            'name' => 'Credit Test Lot',
            'total_slots' => 1,
            'available_slots' => 1,
            'status' => 'open',
        ]);

        ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => '001',
            'status' => 'available',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'start_time' => now()->addHour()->toDateTimeString(),
                'end_time' => now()->addHours(3)->toDateTimeString(),
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits_balance' => 8,
        ]);
    }

    public function test_insufficient_credits_blocks_booking(): void
    {
        $user = User::factory()->create(['role' => 'user', 'credits_balance' => 0]);
        $token = $user->createToken('test')->plainTextToken;

        // Enable credits system
        Setting::set('credits_enabled', 'true');
        Setting::set('credits_per_booking', '1');

        $lot = ParkingLot::create([
            'name' => 'No Credit Lot',
            'total_slots' => 1,
            'available_slots' => 1,
            'status' => 'open',
        ]);

        ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => '001',
            'status' => 'available',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'start_time' => now()->addHour()->toDateTimeString(),
                'end_time' => now()->addHours(3)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INSUFFICIENT_CREDITS');
    }
}
