<?php

namespace Tests\Unit\Models;

use App\Models\BookingNote;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingNoteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_note_has_fillable_attributes(): void
    {
        $model = new BookingNote;
        $this->assertContains('booking_id', $model->getFillable());
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('note', $model->getFillable());
    }

    public function test_booking_note_uses_uuid(): void
    {
        $model = new BookingNote;
        $this->assertTrue(in_array(HasUuids::class, class_uses_recursive($model)));
    }
}
