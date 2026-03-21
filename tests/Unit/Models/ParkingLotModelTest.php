<?php

namespace Tests\Unit\Models;

use App\Models\ParkingLot;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingLotModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_has_fillable_attributes(): void
    {
        $lot = new ParkingLot;
        $this->assertContains('name', $lot->getFillable());
        $this->assertContains('address', $lot->getFillable());
        $this->assertContains('total_slots', $lot->getFillable());
    }

    public function test_lot_has_slots_relationship(): void
    {
        $lot = new ParkingLot;
        $this->assertInstanceOf(HasMany::class, $lot->slots());
    }

    public function test_lot_has_zones_relationship(): void
    {
        $lot = new ParkingLot;
        $this->assertInstanceOf(HasMany::class, $lot->zones());
    }

    public function test_lot_has_bookings_relationship(): void
    {
        $lot = new ParkingLot;
        $this->assertInstanceOf(HasMany::class, $lot->bookings());
    }

    public function test_layout_cast_to_array(): void
    {
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 5,
            'layout' => ['rows' => [['id' => 'r1', 'slots' => []]]],
        ]);

        $this->assertIsArray($lot->layout);
        $this->assertArrayHasKey('rows', $lot->layout);
    }

    public function test_pricing_fields_are_decimal_cast(): void
    {
        $lot = new ParkingLot;
        $casts = $lot->getCasts();
        $this->assertEquals('decimal:2', $casts['hourly_rate']);
        $this->assertEquals('decimal:2', $casts['daily_max']);
        $this->assertEquals('decimal:2', $casts['monthly_pass']);
    }

    public function test_lot_uses_uuid(): void
    {
        $lot = ParkingLot::create(['name' => 'UUID Test', 'total_slots' => 1]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $lot->id);
    }
}
