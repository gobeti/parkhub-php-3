<?php

namespace Tests\Unit\Models;

use App\Models\RecurringBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringBookingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_booking_has_fillable_attributes(): void
    {
        $model = new RecurringBooking;
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('lot_id', $model->getFillable());
        $this->assertContains('slot_id', $model->getFillable());
        $this->assertContains('days_of_week', $model->getFillable());
        $this->assertContains('start_date', $model->getFillable());
        $this->assertContains('end_date', $model->getFillable());
        $this->assertContains('start_time', $model->getFillable());
        $this->assertContains('end_time', $model->getFillable());
        $this->assertContains('vehicle_plate', $model->getFillable());
        $this->assertContains('active', $model->getFillable());
    }

    public function test_days_of_week_cast_to_array(): void
    {
        $model = new RecurringBooking;
        $casts = $model->getCasts();
        $this->assertEquals('array', $casts['days_of_week']);
    }

    public function test_active_cast_to_boolean(): void
    {
        $model = new RecurringBooking;
        $casts = $model->getCasts();
        $this->assertEquals('boolean', $casts['active']);
    }
}
