<?php

namespace Tests\Unit\Models;

use App\Models\SwapRequest;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwapRequestModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_swap_request_has_fillable_attributes(): void
    {
        $model = new SwapRequest;
        $this->assertContains('requester_booking_id', $model->getFillable());
        $this->assertContains('target_booking_id', $model->getFillable());
        $this->assertContains('requester_id', $model->getFillable());
        $this->assertContains('target_id', $model->getFillable());
        $this->assertContains('status', $model->getFillable());
        $this->assertContains('message', $model->getFillable());
    }

    public function test_belongs_to_requester_booking(): void
    {
        $model = new SwapRequest;
        $this->assertInstanceOf(BelongsTo::class, $model->requesterBooking());
    }

    public function test_belongs_to_target_booking(): void
    {
        $model = new SwapRequest;
        $this->assertInstanceOf(BelongsTo::class, $model->targetBooking());
    }

    public function test_belongs_to_requester(): void
    {
        $model = new SwapRequest;
        $this->assertInstanceOf(BelongsTo::class, $model->requester());
    }

    public function test_belongs_to_target(): void
    {
        $model = new SwapRequest;
        $this->assertInstanceOf(BelongsTo::class, $model->target());
    }
}
