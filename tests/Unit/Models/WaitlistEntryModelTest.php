<?php

namespace Tests\Unit\Models;

use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistEntryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_waitlist_entry_has_fillable_attributes(): void
    {
        $model = new WaitlistEntry;
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('lot_id', $model->getFillable());
        $this->assertContains('slot_id', $model->getFillable());
        $this->assertContains('priority', $model->getFillable());
        $this->assertContains('status', $model->getFillable());
        $this->assertContains('notified_at', $model->getFillable());
        $this->assertContains('offer_expires_at', $model->getFillable());
        $this->assertContains('accepted_booking_id', $model->getFillable());
    }

    public function test_datetime_casts(): void
    {
        $model = new WaitlistEntry;
        $casts = $model->getCasts();
        $this->assertEquals('datetime', $casts['notified_at']);
        $this->assertEquals('datetime', $casts['offer_expires_at']);
    }

    public function test_priority_cast_to_integer(): void
    {
        $model = new WaitlistEntry;
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['priority']);
    }

    public function test_belongs_to_user(): void
    {
        $model = new WaitlistEntry;
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_belongs_to_lot(): void
    {
        $model = new WaitlistEntry;
        $this->assertInstanceOf(BelongsTo::class, $model->lot());
    }

    public function test_belongs_to_slot(): void
    {
        $model = new WaitlistEntry;
        $this->assertInstanceOf(BelongsTo::class, $model->slot());
    }
}
