<?php

namespace Tests\Unit\Models;

use App\Models\ParkingSlot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingSlotModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_parking_slot_has_fillable_attributes(): void
    {
        $model = new ParkingSlot;
        $this->assertContains('lot_id', $model->getFillable());
        $this->assertContains('slot_number', $model->getFillable());
        $this->assertContains('status', $model->getFillable());
        $this->assertContains('slot_type', $model->getFillable());
        $this->assertContains('features', $model->getFillable());
        $this->assertContains('reserved_for_department', $model->getFillable());
        $this->assertContains('zone_id', $model->getFillable());
        $this->assertContains('is_accessible', $model->getFillable());
    }

    public function test_features_cast_to_array(): void
    {
        $model = new ParkingSlot;
        $casts = $model->getCasts();
        $this->assertEquals('array', $casts['features']);
    }

    public function test_is_accessible_cast_to_boolean(): void
    {
        $model = new ParkingSlot;
        $casts = $model->getCasts();
        $this->assertEquals('boolean', $casts['is_accessible']);
    }

    public function test_number_attribute_returns_slot_number(): void
    {
        $model = new ParkingSlot;
        $model->slot_number = 'A-42';
        $this->assertEquals('A-42', $model->number);
    }

    public function test_belongs_to_lot(): void
    {
        $model = new ParkingSlot;
        $this->assertInstanceOf(BelongsTo::class, $model->lot());
    }

    public function test_belongs_to_zone(): void
    {
        $model = new ParkingSlot;
        $this->assertInstanceOf(BelongsTo::class, $model->zone());
    }

    public function test_has_many_bookings(): void
    {
        $model = new ParkingSlot;
        $this->assertInstanceOf(HasMany::class, $model->bookings());
    }

    public function test_has_one_active_booking(): void
    {
        $model = new ParkingSlot;
        $this->assertInstanceOf(HasOne::class, $model->activeBooking());
    }
}
