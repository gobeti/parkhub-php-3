<?php

namespace Tests\Unit\Models;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_has_fillable_attributes(): void
    {
        $booking = new Booking;
        $this->assertContains('user_id', $booking->getFillable());
        $this->assertContains('lot_id', $booking->getFillable());
        $this->assertContains('slot_id', $booking->getFillable());
        $this->assertContains('status', $booking->getFillable());
    }

    public function test_booking_status_constants(): void
    {
        $this->assertEquals('confirmed', Booking::STATUS_CONFIRMED);
        $this->assertEquals('active', Booking::STATUS_ACTIVE);
        $this->assertEquals('cancelled', Booking::STATUS_CANCELLED);
        $this->assertEquals('completed', Booking::STATUS_COMPLETED);
        $this->assertEquals('no_show', Booking::STATUS_NO_SHOW);
    }

    public function test_booking_uses_uuid(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '001', 'status' => 'available']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $booking->id);
    }

    public function test_booking_belongs_to_user(): void
    {
        $booking = new Booking;
        $this->assertInstanceOf(BelongsTo::class, $booking->user());
    }

    public function test_booking_belongs_to_lot(): void
    {
        $booking = new Booking;
        $this->assertInstanceOf(BelongsTo::class, $booking->lot());
    }

    public function test_booking_belongs_to_slot(): void
    {
        $booking = new Booking;
        $this->assertInstanceOf(BelongsTo::class, $booking->slot());
    }

    public function test_booking_has_many_notes(): void
    {
        $booking = new Booking;
        $this->assertInstanceOf(HasMany::class, $booking->bookingNotes());
    }

    public function test_start_time_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '001', 'status' => 'available']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => '2026-06-01 08:00:00',
            'end_time' => '2026-06-01 17:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->assertInstanceOf(Carbon::class, $booking->start_time);
        $this->assertInstanceOf(Carbon::class, $booking->end_time);
    }

    public function test_pricing_fields_are_decimal_cast(): void
    {
        $booking = new Booking;
        $casts = $booking->getCasts();
        $this->assertEquals('decimal:2', $casts['base_price']);
        $this->assertEquals('decimal:2', $casts['tax_amount']);
        $this->assertEquals('decimal:2', $casts['total_price']);
    }
}
