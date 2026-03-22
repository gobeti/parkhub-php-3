<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit_log';

    protected $fillable = ['user_id', 'username', 'action', 'event_type', 'details', 'ip_address', 'target_type', 'target_id'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    /**
     * Log an audit entry without ever crashing the caller.
     * If the audit_log table doesn't exist (e.g. demo reset in progress), the entry is silently dropped.
     */
    public static function log(array $attributes): ?self
    {
        try {
            return static::create($attributes);
        } catch (\Throwable) {
            return null;
        }
    }
}
