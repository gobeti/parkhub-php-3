<?php

namespace Tests\Unit\Models;

use App\Models\ParkingPass;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingPassModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_parking_pass_has_fillable_attributes(): void
    {
        $model = new ParkingPass;
        $this->assertContains('booking_id', $model->getFillable());
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('verification_code', $model->getFillable());
        $this->assertContains('status', $model->getFillable());
    }

    public function test_belongs_to_booking(): void
    {
        $model = new ParkingPass;
        $this->assertInstanceOf(BelongsTo::class, $model->booking());
    }

    public function test_belongs_to_user(): void
    {
        $model = new ParkingPass;
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}
