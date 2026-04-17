<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VehicleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_vehicle_owned_by_the_given_user(): void
    {
        $user = User::factory()->create();
        $service = app(VehicleService::class);

        $vehicle = $service->create([
            'plate' => 'M-AB 1234',
            'make' => 'BMW',
            'model' => '320d',
            'color' => 'black',
            'is_default' => true,
        ], $user);

        $this->assertSame($user->id, $vehicle->user_id);
        $this->assertSame('M-AB 1234', $vehicle->plate);
        $this->assertSame('BMW', $vehicle->make);
        $this->assertTrue((bool) $vehicle->is_default);
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'user_id' => $user->id,
            'plate' => 'M-AB 1234',
        ]);
    }

    public function test_create_ignores_non_editable_server_owned_fields(): void
    {
        $user = User::factory()->create();
        $attackerId = (string) Str::uuid();
        $service = app(VehicleService::class);

        $vehicle = $service->create([
            'plate' => 'HH-CD 9999',
            'make' => 'VW',
            // These keys must NEVER reach the model's fillable path -- they
            // are server-owned (ownership, moderation, uploaded media).
            'user_id' => $attackerId,
            'photo_url' => '/api/v1/vehicles/hijack.jpg',
            'flagged' => true,
        ], $user);

        $this->assertSame($user->id, $vehicle->user_id);
        $this->assertNotSame($attackerId, $vehicle->user_id);
        $this->assertNull($vehicle->photo_url);
        $this->assertFalse((bool) $vehicle->flagged);
    }

    public function test_update_applies_only_allowed_fields(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'M-AB 1234',
            'make' => 'BMW',
            'color' => 'black',
        ]);
        $service = app(VehicleService::class);

        $service->update($vehicle, [
            'plate' => 'M-XY 9999',
            'color' => 'red',
            // Must be ignored — would otherwise allow a hijacker to
            // steal a vehicle by attaching to their own user id.
            'user_id' => (string) Str::uuid(),
        ]);

        $fresh = $vehicle->fresh();
        $this->assertSame('M-XY 9999', $fresh->plate);
        $this->assertSame('red', $fresh->color);
        $this->assertSame($user->id, $fresh->user_id);
    }
}
