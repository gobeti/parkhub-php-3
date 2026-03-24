<?php

namespace Tests\Unit\Models;

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_has_fillable_attributes(): void
    {
        $model = new Announcement;
        $this->assertContains('title', $model->getFillable());
        $this->assertContains('message', $model->getFillable());
        $this->assertContains('severity', $model->getFillable());
        $this->assertContains('active', $model->getFillable());
        $this->assertContains('created_by', $model->getFillable());
        $this->assertContains('expires_at', $model->getFillable());
    }

    public function test_active_cast_to_boolean(): void
    {
        $model = new Announcement;
        $casts = $model->getCasts();
        $this->assertEquals('boolean', $casts['active']);
    }

    public function test_expires_at_cast_to_datetime(): void
    {
        $model = new Announcement;
        $casts = $model->getCasts();
        $this->assertEquals('datetime', $casts['expires_at']);
    }

    public function test_announcement_uses_uuid(): void
    {
        $model = Announcement::create([
            'title' => 'Test',
            'message' => 'Test message',
            'severity' => 'info',
            'active' => true,
        ]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $model->id);
    }
}
