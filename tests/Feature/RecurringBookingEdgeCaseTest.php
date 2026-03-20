<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\RecurringBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringBookingEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndLot(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Recurring Edge Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'RE1',
            'status' => 'available',
        ]);

        return [$user, $lot, $slot];
    }

    public function test_recurring_booking_end_time_before_start_time_rejected(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'days_of_week' => [1],
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addMonth()->format('Y-m-d'),
                'start_time' => '17:00',
                'end_time' => '08:00',
            ])
            ->assertStatus(422);
    }

    public function test_recurring_booking_invalid_time_format_rejected(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'days_of_week' => [1],
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addMonth()->format('Y-m-d'),
                'start_time' => '8am',
                'end_time' => '5pm',
            ])
            ->assertStatus(422);
    }

    public function test_recurring_booking_empty_days_rejected(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'days_of_week' => [],
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addMonth()->format('Y-m-d'),
                'start_time' => '08:00',
                'end_time' => '17:00',
            ])
            ->assertStatus(422);
    }

    public function test_update_recurring_booking(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $recurring = RecurringBooking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'days_of_week' => [1, 3],
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/recurring-bookings/'.$recurring->id, [
                'active' => false,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('recurring_bookings', [
            'id' => $recurring->id,
            'active' => false,
        ]);
    }

    public function test_cannot_update_other_users_recurring(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $other = User::factory()->create(['role' => 'user']);
        $otherToken = $other->createToken('test')->plainTextToken;

        $recurring = RecurringBooking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'days_of_week' => [2],
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->putJson('/api/v1/recurring-bookings/'.$recurring->id, ['active' => false])
            ->assertStatus(404);
    }

    public function test_recurring_missing_lot_id_rejected(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/recurring-bookings', [
                'slot_id' => $slot->id,
                'days_of_week' => [1],
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addMonth()->format('Y-m-d'),
                'start_time' => '08:00',
                'end_time' => '17:00',
            ])
            ->assertStatus(422);
    }

    public function test_recurring_missing_slot_id_rejected(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'days_of_week' => [1],
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addMonth()->format('Y-m-d'),
                'start_time' => '08:00',
                'end_time' => '17:00',
            ])
            ->assertStatus(422);
    }

    public function test_list_only_own_recurring_bookings(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $other = User::factory()->create(['role' => 'user']);

        RecurringBooking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'days_of_week' => [1],
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'active' => true,
        ]);

        $otherToken = $other->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->getJson('/api/v1/recurring-bookings');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }
}
