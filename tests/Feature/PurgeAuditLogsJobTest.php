<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PurgeAuditLogsJob;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeAuditLogsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_only_rows_older_than_retention_window(): void
    {
        // 3 rows older than the 90-day window → must be deleted.
        $expiredIds = [];
        for ($i = 0; $i < 3; $i++) {
            $row = AuditLog::create([
                'action' => 'test.expired.'.$i,
                'details' => ['i' => $i],
            ]);
            // Back-date created_at past the retention cutoff.
            $row->created_at = now()->subDays(95 + $i);
            $row->updated_at = $row->created_at;
            $row->saveQuietly();
            $expiredIds[] = $row->id;
        }

        // 7 rows inside the retention window → must survive.
        $freshIds = [];
        for ($i = 0; $i < 7; $i++) {
            $row = AuditLog::create([
                'action' => 'test.fresh.'.$i,
                'details' => ['i' => $i],
            ]);
            $row->created_at = now()->subDays($i); // 0..6 days old
            $row->updated_at = $row->created_at;
            $row->saveQuietly();
            $freshIds[] = $row->id;
        }

        $this->assertSame(10, AuditLog::count(), 'seed sanity check');

        (new PurgeAuditLogsJob(90))->handle();

        $this->assertSame(7, AuditLog::count(), 'only the 7 fresh rows should remain');

        foreach ($expiredIds as $id) {
            $this->assertDatabaseMissing('audit_log', ['id' => $id]);
        }
        foreach ($freshIds as $id) {
            $this->assertDatabaseHas('audit_log', ['id' => $id]);
        }
    }

    public function test_custom_retention_days_respected(): void
    {
        $old = AuditLog::create(['action' => 'test.old']);
        $old->created_at = now()->subDays(10);
        $old->updated_at = $old->created_at;
        $old->saveQuietly();

        (new PurgeAuditLogsJob(7))->handle();

        $this->assertDatabaseMissing('audit_log', ['id' => $old->id]);
    }

    public function test_no_rows_to_purge_is_a_noop(): void
    {
        AuditLog::create(['action' => 'test.recent']);

        (new PurgeAuditLogsJob(90))->handle();

        $this->assertSame(1, AuditLog::count());
    }
}
