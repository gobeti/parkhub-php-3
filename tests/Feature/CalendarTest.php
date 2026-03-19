<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithBooking(array $bookingOverrides = []): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Calendar Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'D1',
            'status' => 'available',
        ]);

        $booking = Booking::create(array_merge([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Calendar Lot',
            'slot_number' => 'D1',
            'start_time' => now()->startOfMonth()->addDays(5)->setHour(9),
            'end_time' => now()->startOfMonth()->addDays(5)->setHour(17),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ], $bookingOverrides));

        return [$user, $lot, $slot, $booking];
    }

    public function test_calendar_events_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/calendar/events');

        $response->assertStatus(401);
    }

    public function test_user_can_get_calendar_events(): void
    {
        [$user, $lot, $slot, $booking] = $this->createUserWithBooking();
        $token = $user->createToken('test')->plainTextToken;

        $from = now()->startOfMonth()->toDateTimeString();
        $to = now()->endOfMonth()->toDateTimeString();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/calendar/events?from={$from}&to={$to}");

        $response->assertStatus(200);
        $events = $response->json('data');
        $this->assertCount(1, $events);
        $this->assertEquals('booking', $events[0]['type']);
        $this->assertEquals($booking->id, $events[0]['id']);
    }

    public function test_calendar_events_only_returns_own_bookings(): void
    {
        [$user1, $lot, $slot, $booking1] = $this->createUserWithBooking();

        // Create another user's booking
        $user2 = User::factory()->create(['role' => 'user']);
        $slot2 = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'D2',
            'status' => 'available',
        ]);
        Booking::create([
            'user_id' => $user2->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot2->id,
            'lot_name' => 'Calendar Lot',
            'slot_number' => 'D2',
            'start_time' => now()->startOfMonth()->addDays(5)->setHour(9),
            'end_time' => now()->startOfMonth()->addDays(5)->setHour(17),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $token = $user1->createToken('test')->plainTextToken;

        $from = now()->startOfMonth()->toDateTimeString();
        $to = now()->endOfMonth()->toDateTimeString();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/calendar/events?from={$from}&to={$to}");

        $response->assertStatus(200);
        $events = $response->json('data');
        // Should only see user1's booking
        $this->assertCount(1, $events);
        $this->assertEquals($booking1->id, $events[0]['id']);
    }

    public function test_calendar_events_respects_date_range(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Range Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'E1',
            'status' => 'available',
        ]);

        // Booking in current month
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Range Lot',
            'slot_number' => 'E1',
            'start_time' => now()->startOfMonth()->addDays(2)->setHour(9),
            'end_time' => now()->startOfMonth()->addDays(2)->setHour(17),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        // Booking in next month (outside range)
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Range Lot',
            'slot_number' => 'E1',
            'start_time' => now()->addMonth()->startOfMonth()->addDays(10)->setHour(9),
            'end_time' => now()->addMonth()->startOfMonth()->addDays(10)->setHour(17),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $from = now()->startOfMonth()->toDateTimeString();
        $to = now()->endOfMonth()->toDateTimeString();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/calendar/events?from={$from}&to={$to}");

        $response->assertStatus(200);
        $events = $response->json('data');
        // Only the current month booking
        $this->assertCount(1, $events);
    }

    public function test_calendar_events_structure(): void
    {
        [$user] = $this->createUserWithBooking();
        $token = $user->createToken('test')->plainTextToken;

        $from = now()->startOfMonth()->toDateTimeString();
        $to = now()->endOfMonth()->toDateTimeString();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/calendar/events?from={$from}&to={$to}");

        $response->assertStatus(200);
        $events = $response->json('data');
        $this->assertArrayHasKey('id', $events[0]);
        $this->assertArrayHasKey('title', $events[0]);
        $this->assertArrayHasKey('start', $events[0]);
        $this->assertArrayHasKey('end', $events[0]);
        $this->assertArrayHasKey('type', $events[0]);
        $this->assertArrayHasKey('status', $events[0]);
    }

    public function test_calendar_events_returns_empty_for_no_bookings(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $from = now()->startOfMonth()->toDateTimeString();
        $to = now()->endOfMonth()->toDateTimeString();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/calendar/events?from={$from}&to={$to}");

        $response->assertStatus(200);
        $events = $response->json('data');
        $this->assertCount(0, $events);
    }
}
