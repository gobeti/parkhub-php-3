<?php

namespace Tests\Unit\Models;

use App\Models\ChargingSession;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingSessionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_charging_session_has_fillable_attributes(): void
    {
        $model = new ChargingSession;
        $this->assertContains('charger_id', $model->getFillable());
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('start_time', $model->getFillable());
        $this->assertContains('end_time', $model->getFillable());
        $this->assertContains('kwh_consumed', $model->getFillable());
        $this->assertContains('status', $model->getFillable());
    }

    public function test_datetime_casts(): void
    {
        $model = new ChargingSession;
        $casts = $model->getCasts();
        $this->assertEquals('datetime', $casts['start_time']);
        $this->assertEquals('datetime', $casts['end_time']);
    }

    public function test_kwh_consumed_cast_to_float(): void
    {
        $model = new ChargingSession;
        $casts = $model->getCasts();
        $this->assertEquals('float', $casts['kwh_consumed']);
    }

    public function test_belongs_to_charger(): void
    {
        $model = new ChargingSession;
        $this->assertInstanceOf(BelongsTo::class, $model->charger());
    }

    public function test_belongs_to_user(): void
    {
        $model = new ChargingSession;
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}
