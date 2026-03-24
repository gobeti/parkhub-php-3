<?php

namespace Tests\Unit\Models;

use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_subscription_has_fillable_attributes(): void
    {
        $model = new PushSubscription;
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('endpoint', $model->getFillable());
        $this->assertContains('p256dh', $model->getFillable());
        $this->assertContains('auth', $model->getFillable());
    }

    public function test_push_subscription_uses_uuid(): void
    {
        $model = new PushSubscription;
        $this->assertTrue(in_array(HasUuids::class, class_uses_recursive($model)));
    }
}
