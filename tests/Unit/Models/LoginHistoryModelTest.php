<?php

namespace Tests\Unit\Models;

use App\Models\LoginHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHistoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_history_has_fillable_attributes(): void
    {
        $model = new LoginHistory;
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('ip_address', $model->getFillable());
        $this->assertContains('user_agent', $model->getFillable());
        $this->assertContains('logged_in_at', $model->getFillable());
    }

    public function test_uses_custom_table(): void
    {
        $model = new LoginHistory;
        $this->assertEquals('login_history', $model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $model = new LoginHistory;
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_logged_in_at_cast_to_datetime(): void
    {
        $model = new LoginHistory;
        $casts = $model->getCasts();
        $this->assertEquals('datetime', $casts['logged_in_at']);
    }

    public function test_belongs_to_user(): void
    {
        $model = new LoginHistory;
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}
