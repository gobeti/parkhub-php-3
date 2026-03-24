<?php

namespace Tests\Unit\Models;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_has_fillable_attributes(): void
    {
        $model = new Notification;
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('type', $model->getFillable());
        $this->assertContains('title', $model->getFillable());
        $this->assertContains('message', $model->getFillable());
        $this->assertContains('data', $model->getFillable());
        $this->assertContains('read', $model->getFillable());
    }

    public function test_uses_custom_table(): void
    {
        $model = new Notification;
        $this->assertEquals('notifications_custom', $model->getTable());
    }

    public function test_data_cast_to_array(): void
    {
        $model = new Notification;
        $casts = $model->getCasts();
        $this->assertEquals('array', $casts['data']);
    }

    public function test_read_cast_to_boolean(): void
    {
        $model = new Notification;
        $casts = $model->getCasts();
        $this->assertEquals('boolean', $casts['read']);
    }
}
