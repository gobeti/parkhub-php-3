<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Booking;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\Booking\BookingCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'B7',
            'status' => 'available',
        ]);

        return [$user, $lot, $slot];
    }

    public function test_happy_path_creates_booking_and_returns_ok_result(): void
    {
        [$user, $lot, $slot] = $this->scenario();
        $service = app(BookingCreationService::class);

        $result = $service->create([
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour()->toDateTimeString(),
            'end_time' => now()->addHours(3)->toDateTimeString(),
            'booking_type' => 'einmalig',
        ], $user);

        $this->assertTrue($result->isOk(), 'expected ok result');
        $this->assertSame(201, $result->status);
        $this->assertNotNull($result->booking);
        $this->assertSame($user->id, $result->booking->user_id);
        $this->assertSame($slot->id, $result->booking->slot_id);
        $this->assertSame(Booking::STATUS_CONFIRMED, $result->booking->status);
        $this->assertDatabaseHas('bookings', ['id' => $result->booking->id]);
    }

    public function test_rejects_booking_that_starts_in_the_past(): void
    {
        [$user, $lot, $slot] = $this->scenario();
        $service = app(BookingCreationService::class);

        $result = $service->create([
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subHour()->toDateTimeString(),
            'end_time' => now()->addHour()->toDateTimeString(),
        ], $user);

        $this->assertFalse($result->isOk());
        $this->assertSame('INVALID_BOOKING_TIME', $result->errorCode);
        $this->assertSame(422, $result->status);
        $this->assertDatabaseMissing('bookings', ['user_id' => $user->id]);
    }

    public function test_returns_conflict_when_slot_already_booked(): void
    {
        [$user, $lot, $slot] = $this->scenario();

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(4),
            'status' => Booking::STATUS_CONFIRMED,
            'booking_type' => 'einmalig',
        ]);

        $other = User::factory()->create(['role' => 'user']);
        $service = app(BookingCreationService::class);

        $result = $service->create([
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHours(2)->toDateTimeString(),
            'end_time' => now()->addHours(3)->toDateTimeString(),
        ], $other);

        $this->assertFalse($result->isOk());
        $this->assertSame(409, $result->status);
        $this->assertContains($result->errorCode, ['SLOT_UNAVAILABLE', 'NO_SLOTS_AVAILABLE']);
    }
}
