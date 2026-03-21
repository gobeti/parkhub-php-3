<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_has_fillable_attributes(): void
    {
        $vehicle = new Vehicle;
        $this->assertContains('plate', $vehicle->getFillable());
        $this->assertContains('make', $vehicle->getFillable());
        $this->assertContains('model', $vehicle->getFillable());
        $this->assertContains('color', $vehicle->getFillable());
        $this->assertContains('user_id', $vehicle->getFillable());
    }

    public function test_vehicle_belongs_to_user(): void
    {
        $vehicle = new Vehicle;
        $this->assertInstanceOf(BelongsTo::class, $vehicle->user());
    }

    public function test_is_default_cast_to_boolean(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'AB-CD-1234',
            'is_default' => true,
        ]);

        $this->assertIsBool($vehicle->is_default);
        $this->assertTrue($vehicle->is_default);
    }

    public function test_vehicle_uses_uuid(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'XY-ZZ-9999',
        ]);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $vehicle->id);
    }
}
