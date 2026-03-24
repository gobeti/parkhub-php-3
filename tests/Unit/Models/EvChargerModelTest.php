<?php

namespace Tests\Unit\Models;

use App\Models\EvCharger;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvChargerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_ev_charger_has_fillable_attributes(): void
    {
        $model = new EvCharger;
        $this->assertContains('lot_id', $model->getFillable());
        $this->assertContains('label', $model->getFillable());
        $this->assertContains('connector_type', $model->getFillable());
        $this->assertContains('power_kw', $model->getFillable());
        $this->assertContains('status', $model->getFillable());
        $this->assertContains('location_hint', $model->getFillable());
    }

    public function test_power_kw_cast_to_float(): void
    {
        $model = new EvCharger;
        $casts = $model->getCasts();
        $this->assertEquals('float', $casts['power_kw']);
    }

    public function test_belongs_to_lot(): void
    {
        $model = new EvCharger;
        $this->assertInstanceOf(BelongsTo::class, $model->lot());
    }

    public function test_has_many_sessions(): void
    {
        $model = new EvCharger;
        $this->assertInstanceOf(HasMany::class, $model->sessions());
    }
}
