<?php

namespace Tests\Unit\Models;

use App\Models\GuestBooking;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestBookingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_booking_has_fillable_attributes(): void
    {
        $model = new GuestBooking;
        $this->assertContains('created_by', $model->getFillable());
        $this->assertContains('lot_id', $model->getFillable());
        $this->assertContains('slot_id', $model->getFillable());
        $this->assertContains('guest_name', $model->getFillable());
        $this->assertContains('guest_code', $model->getFillable());
        $this->assertContains('start_time', $model->getFillable());
        $this->assertContains('end_time', $model->getFillable());
        $this->assertContains('vehicle_plate', $model->getFillable());
        $this->assertContains('status', $model->getFillable());
    }

    public function test_datetime_casts(): void
    {
        $model = new GuestBooking;
        $casts = $model->getCasts();
        $this->assertEquals('datetime', $casts['start_time']);
        $this->assertEquals('datetime', $casts['end_time']);
    }

    public function test_belongs_to_lot(): void
    {
        $model = new GuestBooking;
        $this->assertInstanceOf(BelongsTo::class, $model->lot());
    }

    public function test_belongs_to_slot(): void
    {
        $model = new GuestBooking;
        $this->assertInstanceOf(BelongsTo::class, $model->slot());
    }

    public function test_belongs_to_creator(): void
    {
        $model = new GuestBooking;
        $this->assertInstanceOf(BelongsTo::class, $model->creator());
    }
}
