<?php

namespace Tests\Unit\Models;

use App\Models\Absence;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_absence_has_fillable_attributes(): void
    {
        $absence = new Absence;
        $fillable = $absence->getFillable();
        $this->assertContains('user_id', $fillable);
        $this->assertContains('absence_type', $fillable);
        $this->assertContains('start_date', $fillable);
        $this->assertContains('end_date', $fillable);
    }

    public function test_absence_belongs_to_user(): void
    {
        $absence = new Absence;
        $this->assertInstanceOf(BelongsTo::class, $absence->user());
    }

    public function test_absence_uses_uuid(): void
    {
        $user = User::factory()->create();
        $absence = Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'homeoffice',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(1)->toDateString(),
        ]);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $absence->id);
    }

    public function test_absence_creation(): void
    {
        $user = User::factory()->create();
        $absence = Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'vacation',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-14',
            'note' => 'Summer holiday',
        ]);

        $this->assertDatabaseHas('absences', [
            'user_id' => $user->id,
            'absence_type' => 'vacation',
        ]);
    }
}
