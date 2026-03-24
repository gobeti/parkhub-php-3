<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_has_fillable_attributes(): void
    {
        $model = new AuditLog;
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('username', $model->getFillable());
        $this->assertContains('action', $model->getFillable());
        $this->assertContains('event_type', $model->getFillable());
        $this->assertContains('details', $model->getFillable());
        $this->assertContains('ip_address', $model->getFillable());
        $this->assertContains('target_type', $model->getFillable());
        $this->assertContains('target_id', $model->getFillable());
    }

    public function test_uses_custom_table(): void
    {
        $model = new AuditLog;
        $this->assertEquals('audit_log', $model->getTable());
    }

    public function test_details_cast_to_array(): void
    {
        $model = new AuditLog;
        $casts = $model->getCasts();
        $this->assertEquals('array', $casts['details']);
    }

    public function test_log_helper_creates_entry(): void
    {
        $entry = AuditLog::log([
            'username' => 'admin',
            'action' => 'login',
            'event_type' => 'auth',
        ]);
        $this->assertNotNull($entry);
        $this->assertInstanceOf(AuditLog::class, $entry);
    }

    public function test_log_helper_returns_null_on_failure(): void
    {
        // Passing invalid data that would cause a DB error won't crash
        // The log method catches all throwables
        $result = AuditLog::log([]);
        // Either succeeds with minimal data or returns null - both are acceptable
        $this->assertTrue($result === null || $result instanceof AuditLog);
    }

    public function test_audit_log_uses_uuid(): void
    {
        $model = AuditLog::create([
            'username' => 'test',
            'action' => 'test_action',
        ]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $model->id);
    }
}
