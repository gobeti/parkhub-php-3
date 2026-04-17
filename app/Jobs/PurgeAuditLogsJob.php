<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Enforces the GDPR audit-log retention window documented in docs/GDPR.md.
 *
 * Deletes audit_log rows older than `$retentionDays` in chunks of
 * CHUNK_SIZE, using Eloquent so any future global scope on AuditLog
 * (e.g. a tenant scope) still applies. Scheduled daily at 03:15 UTC
 * via routes/console.php with ->onOneServer()->withoutOverlapping().
 */
class PurgeAuditLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Delete in chunks to avoid long-running transactions and to keep the
     * binlog / replication stream healthy on shared-hosting MySQL boxes.
     */
    private const CHUNK_SIZE = 1000;

    /**
     * @param  int  $retentionDays  Days to keep audit_log rows (default 90, see docs/GDPR.md).
     */
    public function __construct(
        private int $retentionDays = 90,
    ) {}

    public function handle(): void
    {
        $cutoff = now()->subDays($this->retentionDays);
        $totalDeleted = 0;

        try {
            // chunkById on a "expired rows" query is a self-shrinking loop:
            // each iteration deletes the batch we just pulled, so the next
            // ->take(CHUNK_SIZE) finds a fresh batch. Loop exits when the
            // query returns zero rows.
            while (true) {
                $batchIds = AuditLog::where('created_at', '<', $cutoff)
                    ->orderBy('id')
                    ->limit(self::CHUNK_SIZE)
                    ->pluck('id');

                if ($batchIds->isEmpty()) {
                    break;
                }

                $deleted = AuditLog::whereIn('id', $batchIds)->delete();
                $totalDeleted += $deleted;

                $oldestRemaining = AuditLog::where('created_at', '<', $cutoff)
                    ->orderBy('created_at')
                    ->value('created_at');

                Log::info('PurgeAuditLogsJob: chunk deleted', [
                    'deleted_count' => $deleted,
                    'total_deleted' => $totalDeleted,
                    'retention_days' => $this->retentionDays,
                    'cutoff' => $cutoff->toIso8601String(),
                    'oldest_remaining' => $oldestRemaining?->toIso8601String(),
                ]);

                // Defensive: if a delete returns fewer rows than we pulled
                // (e.g. concurrent purge), we still move forward because the
                // next iteration re-queries. If zero rows were actually
                // deleted, bail to avoid an infinite loop.
                if ($deleted === 0) {
                    break;
                }
            }

            Log::info('PurgeAuditLogsJob: complete', [
                'total_deleted' => $totalDeleted,
                'retention_days' => $this->retentionDays,
            ]);
        } catch (Throwable $e) {
            Log::error('PurgeAuditLogsJob: failed', [
                'error' => $e->getMessage(),
                'total_deleted_before_failure' => $totalDeleted,
                'retention_days' => $this->retentionDays,
            ]);
            // Re-throw so Laravel's queue worker records the failure and
            // retries according to the worker config.
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('PurgeAuditLogsJob: marked failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
