<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_has_fillable_attributes(): void
    {
        $model = new Tenant;
        $this->assertContains('id', $model->getFillable());
        $this->assertContains('name', $model->getFillable());
        $this->assertContains('domain', $model->getFillable());
        $this->assertContains('branding', $model->getFillable());
    }

    public function test_branding_cast_to_array(): void
    {
        $model = new Tenant;
        $casts = $model->getCasts();
        $this->assertEquals('array', $casts['branding']);
    }

    public function test_has_many_users(): void
    {
        $model = new Tenant;
        $this->assertInstanceOf(HasMany::class, $model->users());
    }

    public function test_has_many_parking_lots(): void
    {
        $model = new Tenant;
        $this->assertInstanceOf(HasMany::class, $model->parkingLots());
    }

    public function test_has_many_bookings(): void
    {
        $model = new Tenant;
        $this->assertInstanceOf(HasMany::class, $model->bookings());
    }
}
