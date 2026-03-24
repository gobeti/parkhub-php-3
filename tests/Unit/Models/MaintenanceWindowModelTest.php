<?php

namespace Tests\Unit\Models;

use App\Models\MaintenanceWindow;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceWindowModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_window_has_fillable_attributes(): void
    {
        $model = new MaintenanceWindow;
        $this->assertContains('lot_id', $model->getFillable());
        $this->assertContains('start_time', $model->getFillable());
        $this->assertContains('end_time', $model->getFillable());
        $this->assertContains('reason', $model->getFillable());
        $this->assertContains('affected_slots', $model->getFillable());
    }

    public function test_datetime_casts(): void
    {
        $model = new MaintenanceWindow;
        $casts = $model->getCasts();
        $this->assertEquals('datetime', $casts['start_time']);
        $this->assertEquals('datetime', $casts['end_time']);
    }

    public function test_affected_slots_cast_to_array(): void
    {
        $model = new MaintenanceWindow;
        $casts = $model->getCasts();
        $this->assertEquals('array', $casts['affected_slots']);
    }

    public function test_belongs_to_lot(): void
    {
        $model = new MaintenanceWindow;
        $this->assertInstanceOf(BelongsTo::class, $model->lot());
    }
}
