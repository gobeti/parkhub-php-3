<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owns the read-side of the audit log surface extracted from
 * AuditLogController (T-1742, pass 6 — final).
 *
 * Pure extraction — filter construction (action / user / from / to),
 * pagination clamping, entry mapping and CSV / JSON / PDF export
 * rendering all match the previous inline controller implementation.
 * Retention / purge lives in PurgeAuditLogsJob (T-1745) and is
 * deliberately not repeated here. Controllers remain responsible for
 * module gates, admin gating and HTTP response shaping.
 */
final class AuditLogQueryService
{
    /**
     * Upper bound on the per_page query parameter — matches the
     * previous inline guard in AuditLogController::index.
     */
    public const int MAX_PER_PAGE = 100;

    /**
     * Default page size when the client omits per_page.
     */
    public const int DEFAULT_PER_PAGE = 25;

    /**
     * Build a filtered + ordered audit log query from request params.
     *
     * The `user` filter is a partial username match OR an exact user_id
     * match; `user_id` (enhanced export) is always an exact user_id
     * match. Both are kept to preserve the two export-time contracts.
     */
    public function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        if ($action = $request->query('action')) {
            $query->where(function ($q) use ($action) {
                $q->where('event_type', $action)->orWhere('action', $action);
            });
        }

        if ($user = $request->query('user')) {
            $query->where(function ($q) use ($user) {
                $q->where('username', 'like', "%{$user}%")
                    ->orWhere('user_id', $user);
            });
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        return $query;
    }

    /**
     * Paginate the filtered audit log into the canonical response
     * envelope expected by GET /api/v1/admin/audit-log. The controller
     * adds the outer `{success, data}` envelope; the OpenAPI shape is
     * annotated on the controller index via `@scramble-return`.
     *
     * @return array<string, mixed>
     */
    public function paginate(Request $request): array
    {
        $perPage = min((int) $request->query('per_page', (string) self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        $page = max((int) $request->query('page', '1'), 1);

        $query = $this->filteredQuery($request);
        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $entries = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return [
            'entries' => $entries->map(fn (AuditLog $e) => $this->mapEntry($e, stringifyDetails: true))->values()->all(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Stream the filtered audit log as RFC-4180 CSV for the legacy
     * GET /api/v1/admin/audit-log/export endpoint.
     */
    public function streamCsv(Request $request): StreamedResponse
    {
        $entries = $this->filteredQuery($request)->get();

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Timestamp', 'Event Type', 'User ID', 'Username', 'Target Type', 'Target ID', 'IP Address', 'Details']);

            foreach ($entries as $e) {
                fputcsv($out, $this->csvRow($e));
            }

            fclose($out);
        }, 'audit-log.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Stream the filtered audit log as pretty-printed JSON for the
     * enhanced export in JSON mode.
     */
    public function streamJson(Request $request): StreamedResponse
    {
        $mapped = $this->mappedCollection($request);

        return response()->streamDownload(function () use ($mapped) {
            echo json_encode([
                'exported_at' => now()->toISOString(),
                'count' => $mapped->count(),
                'entries' => $mapped,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, 'audit-log.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    /**
     * Stream the filtered audit log as a plain-text PDF-adjacent
     * representation. Full PDF rendering would require a library like
     * dompdf — the text form preserves the legacy behaviour from the
     * enhanced-export endpoint.
     */
    public function streamPdf(Request $request): StreamedResponse
    {
        $mapped = $this->mappedCollection($request);

        return response()->streamDownload(function () use ($mapped) {
            echo "AUDIT LOG EXPORT\n";
            echo 'Generated: '.now()->toISOString()."\n";
            echo 'Entries: '.$mapped->count()."\n";
            echo str_repeat('=', 80)."\n\n";

            foreach ($mapped as $entry) {
                echo "[$entry[timestamp]] $entry[event_type] — $entry[username] ($entry[ip_address])\n";
                if ($entry['target_type']) {
                    echo "  Target: $entry[target_type]:$entry[target_id]\n";
                }
                if ($entry['details']) {
                    echo "  Details: $entry[details]\n";
                }
                echo "\n";
            }
        }, 'audit-log.txt', [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Materialise the filtered audit log as an enumerable collection
     * of mapped rows, used by the JSON and PDF exporters.
     */
    private function mappedCollection(Request $request): Collection
    {
        return $this->filteredQuery($request)
            ->get()
            ->map(fn (AuditLog $e) => $this->mapEntry($e, stringifyDetails: true))
            ->values();
    }

    /**
     * Shape a single audit log row into the API response envelope.
     *
     * @return array<string, mixed>
     */
    private function mapEntry(AuditLog $e, bool $stringifyDetails): array
    {
        return [
            'id' => $e->id,
            'timestamp' => $e->created_at?->toISOString(),
            'event_type' => $e->event_type ?? $e->action,
            'user_id' => $e->user_id,
            'username' => $e->username,
            'target_type' => $e->target_type,
            'target_id' => $e->target_id,
            'ip_address' => $e->ip_address,
            'details' => $stringifyDetails && is_array($e->details)
                ? json_encode($e->details)
                : $e->details,
        ];
    }

    /**
     * Shape a single audit log row as a flat CSV record.
     *
     * @return array<int, mixed>
     */
    private function csvRow(AuditLog $e): array
    {
        return [
            $e->created_at?->toISOString(),
            $e->event_type ?? $e->action,
            $e->user_id,
            $e->username,
            $e->target_type,
            $e->target_id,
            $e->ip_address,
            is_array($e->details) ? json_encode($e->details) : $e->details,
        ];
    }
}
