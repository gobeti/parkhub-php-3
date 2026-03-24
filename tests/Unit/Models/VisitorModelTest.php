<?php

namespace Tests\Unit\Models;

use App\Models\Visitor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_has_fillable_attributes(): void
    {
        $model = new Visitor;
        $this->assertContains('host_user_id', $model->getFillable());
        $this->assertContains('name', $model->getFillable());
        $this->assertContains('email', $model->getFillable());
        $this->assertContains('vehicle_plate', $model->getFillable());
        $this->assertContains('visit_date', $model->getFillable());
        $this->assertContains('purpose', $model->getFillable());
        $this->assertContains('status', $model->getFillable());
        $this->assertContains('qr_code', $model->getFillable());
        $this->assertContains('pass_url', $model->getFillable());
        $this->assertContains('checked_in_at', $model->getFillable());
    }

    public function test_datetime_casts(): void
    {
        $model = new Visitor;
        $casts = $model->getCasts();
        $this->assertEquals('datetime', $casts['visit_date']);
        $this->assertEquals('datetime', $casts['checked_in_at']);
    }

    public function test_belongs_to_host(): void
    {
        $model = new Visitor;
        $this->assertInstanceOf(BelongsTo::class, $model->host());
    }
}
