<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\RecurringBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringBookingTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndLot(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Recurring Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'R1',
            'status' => 'available',
        ]);

        return [$user, $lot, $slot];
    }

    public function test_create_recurring_booking_pattern(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'days_of_week' => [1, 3, 5],
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addMonths(3)->format('Y-m-d'),
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('recurring_bookings', [
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'active' => true,
        ]);
    }

    public function test_list_recurring_bookings(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        RecurringBooking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'days_of_week' => [1, 2],
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '18:00',
            'active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/recurring-bookings');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_delete_recurring_pattern(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $recurring = RecurringBooking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'days_of_week' => [4],
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/recurring-bookings/'.$recurring->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('recurring_bookings', ['id' => $recurring->id]);
    }

    public function test_validation_start_date_must_be_today_or_later(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'days_of_week' => [1],
                'start_date' => now()->subWeek()->format('Y-m-d'),
                'end_date' => now()->addMonth()->format('Y-m-d'),
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);

        $response->assertStatus(422);
    }

    public function test_validation_days_of_week_required(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addMonth()->format('Y-m-d'),
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);

        $response->assertStatus(422);
    }

    public function test_validation_end_date_must_be_after_start_date(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'days_of_week' => [1, 3],
                'start_date' => now()->addMonth()->format('Y-m-d'),
                'end_date' => now()->addDay()->format('Y-m-d'),
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);

        $response->assertStatus(422);
    }

    public function test_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/recurring-bookings');
        $response->assertStatus(401);
    }

    public function test_cannot_delete_another_users_recurring_booking(): void
    {
        [$user, $lot, $slot] = $this->createUserAndLot();
        $other = User::factory()->create(['role' => 'user']);

        $recurring = RecurringBooking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'days_of_week' => [2],
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'active' => true,
        ]);

        $otherToken = $other->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->deleteJson('/api/v1/recurring-bookings/'.$recurring->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('recurring_bookings', ['id' => $recurring->id]);
    }
}
