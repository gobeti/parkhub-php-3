<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCompletedBooking(User $user, ?ParkingLot $lot = null, string $status = 'completed'): Booking
    {
        $lot ??= ParkingLot::create([
            'name' => 'Garage Alpha',
            'total_slots' => 10,
            'available_slots' => 5,
            'status' => 'open',
        ]);

        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'A'.rand(1, 99),
            'status' => 'available',
        ]);

        return Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHour(),
            'status' => $status,
            'total_price' => 5,
            'currency' => 'EUR',
        ]);
    }

    public function test_unauthenticated_cannot_access_history(): void
    {
        $response = $this->getJson('/api/v1/bookings/history');
        $response->assertStatus(401);
    }

    public function test_returns_paginated_history(): void
    {
        $user = User::factory()->create();
        $this->createCompletedBooking($user);
        $this->createCompletedBooking($user, status: 'cancelled');

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/history');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.page', 1)
            ->assertJsonPath('data.per_page', 10);
    }

    public function test_filters_by_lot_id(): void
    {
        $user = User::factory()->create();
        $lot1 = ParkingLot::create(['name' => 'Lot A', 'total_slots' => 10, 'available_slots' => 5, 'status' => 'open']);
        $lot2 = ParkingLot::create(['name' => 'Lot B', 'total_slots' => 10, 'available_slots' => 5, 'status' => 'open']);

        $this->createCompletedBooking($user, $lot1);
        $this->createCompletedBooking($user, $lot1);
        $this->createCompletedBooking($user, $lot2);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/history?lot_id='.$lot1->id);

        $response->assertOk()
            ->assertJsonPath('data.total', 2);
    }

    public function test_filters_by_date_range(): void
    {
        $user = User::factory()->create();
        $this->createCompletedBooking($user);

        $from = now()->subDays(7)->toISOString();
        $to = now()->addDay()->toISOString();

        $response = $this->actingAs($user)->getJson("/api/v1/bookings/history?from={$from}&to={$to}");

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_does_not_include_active_bookings(): void
    {
        $user = User::factory()->create();
        $this->createCompletedBooking($user, status: 'active');

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/history');

        $response->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_pagination_works(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 15; $i++) {
            $this->createCompletedBooking($user);
        }

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/history?per_page=5&page=2');

        $response->assertOk()
            ->assertJsonPath('data.page', 2)
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.total', 15)
            ->assertJsonPath('data.total_pages', 3);
    }

    public function test_unauthenticated_cannot_access_stats(): void
    {
        $response = $this->getJson('/api/v1/bookings/stats');
        $response->assertStatus(401);
    }

    public function test_returns_stats(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Garage Alpha', 'total_slots' => 10, 'available_slots' => 5, 'status' => 'open']);
        $this->createCompletedBooking($user, $lot);
        $this->createCompletedBooking($user, $lot);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_bookings', 2)
            ->assertJsonPath('data.favorite_lot', 'Garage Alpha')
            ->assertJsonStructure([
                'data' => [
                    'total_bookings',
                    'favorite_lot',
                    'avg_duration_minutes',
                    'busiest_day',
                    'credits_spent',
                    'monthly_trend',
                ],
            ]);
    }

    public function test_stats_with_no_bookings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_bookings', 0)
            ->assertJsonPath('data.favorite_lot', null)
            ->assertJsonPath('data.credits_spent', 0);
    }

    public function test_disabled_history_module_returns_404(): void
    {
        config(['modules.history' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/bookings/history')->assertNotFound();
        $this->actingAs($user)->getJson('/api/v1/bookings/stats')->assertNotFound();
    }
}
