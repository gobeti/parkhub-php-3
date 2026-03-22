<?php

namespace Tests\Unit\Models;

use App\Models\ParkingLot;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_has_fillable_attributes(): void
    {
        $zone = new Zone;
        $fillable = $zone->getFillable();
        $this->assertContains('lot_id', $fillable);
        $this->assertContains('name', $fillable);
        $this->assertContains('color', $fillable);
        $this->assertContains('description', $fillable);
    }

    public function test_zone_uses_uuid(): void
    {
        $lot = ParkingLot::create(['name' => 'Test Lot', 'total_slots' => 5]);
        $zone = Zone::create(['lot_id' => $lot->id, 'name' => 'Test Zone']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $zone->id);
    }

    public function test_zone_belongs_to_lot(): void
    {
        $zone = new Zone;
        $this->assertInstanceOf(BelongsTo::class, $zone->lot());
    }

    public function test_zone_has_many_slots(): void
    {
        $zone = new Zone;
        $this->assertInstanceOf(HasMany::class, $zone->slots());
    }

    public function test_zone_creation(): void
    {
        $lot = ParkingLot::create(['name' => 'Test Lot', 'total_slots' => 10]);
        $zone = Zone::create([
            'lot_id' => $lot->id,
            'name' => 'VIP Zone',
            'color' => '#FFD700',
            'description' => 'Premium parking',
        ]);

        $this->assertDatabaseHas('zones', [
            'name' => 'VIP Zone',
            'color' => '#FFD700',
        ]);
        $this->assertEquals($lot->id, $zone->lot->id);
    }
}
