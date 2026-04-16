<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sanity checks for the `BelongsToTenant` global scope.
 *
 * The scope is gated on `config('modules.multi_tenant')` so it has to
 * stay a no-op in the default build; it also has to actually kick in
 * when the flag and a `current_tenant` container binding are present.
 */
class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenants(): array
    {
        $a = Tenant::create(['id' => 'tenant-a', 'name' => 'Tenant A']);
        $b = Tenant::create(['id' => 'tenant-b', 'name' => 'Tenant B']);

        return [$a, $b];
    }

    public function test_scope_is_noop_when_multi_tenant_disabled(): void
    {
        config(['modules.multi_tenant' => false]);
        [$tenantA] = $this->seedTenants();

        // Bind a tenant — the scope should ignore it when the feature
        // flag is off.
        app()->instance('current_tenant', $tenantA);

        $lotA = ParkingLot::factory()->create(['tenant_id' => 'tenant-a']);
        $lotB = ParkingLot::factory()->create(['tenant_id' => 'tenant-b']);

        $all = ParkingLot::query()->pluck('id');
        $this->assertContains($lotA->id, $all);
        $this->assertContains($lotB->id, $all);
    }

    public function test_scope_filters_by_current_tenant_when_enabled(): void
    {
        config(['modules.multi_tenant' => true]);
        [$tenantA] = $this->seedTenants();

        app()->instance('current_tenant', $tenantA);

        $lotA = ParkingLot::factory()->create(['tenant_id' => 'tenant-a']);
        $lotB = ParkingLot::factory()->create(['tenant_id' => 'tenant-b']);

        // Dump everything with & without the scope to surface drift; one or
        // the other would otherwise get masked if the scope silently no-ops.
        $unscoped = ParkingLot::withoutGlobalScopes()->pluck('tenant_id', 'id');
        $this->assertEquals('tenant-a', $unscoped[$lotA->id] ?? null, 'lotA tenant_id wrong');
        $this->assertEquals('tenant-b', $unscoped[$lotB->id] ?? null, 'lotB tenant_id wrong');

        $scoped = ParkingLot::query()->pluck('id');
        $this->assertContains($lotA->id, $scoped);
        $this->assertNotContains($lotB->id, $scoped);
    }

    public function test_scope_noop_when_no_tenant_bound_in_container(): void
    {
        config(['modules.multi_tenant' => true]);
        $this->seedTenants();
        app()->forgetInstance('current_tenant');

        $lotA = ParkingLot::factory()->create(['tenant_id' => 'tenant-a']);
        $lotB = ParkingLot::factory()->create(['tenant_id' => 'tenant-b']);

        $all = ParkingLot::query()->pluck('id');
        $this->assertContains($lotA->id, $all);
        $this->assertContains($lotB->id, $all);
    }

    public function test_booking_model_inherits_tenant_scope(): void
    {
        config(['modules.multi_tenant' => true]);
        [$tenantA] = $this->seedTenants();
        app()->instance('current_tenant', $tenantA);

        $user = User::factory()->create(['tenant_id' => 'tenant-a']);
        $lot = ParkingLot::factory()->create(['tenant_id' => 'tenant-a']);
        $slot = ParkingSlot::factory()->create(['lot_id' => $lot->id]);

        $bookingA = Booking::factory()->create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'tenant_id' => 'tenant-a',
        ]);
        $bookingB = Booking::factory()->create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'tenant_id' => 'tenant-b',
        ]);

        $scoped = Booking::query()->pluck('id');
        $this->assertContains($bookingA->id, $scoped);
        $this->assertNotContains($bookingB->id, $scoped);
    }
}
