<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * GET /api/v1/admin/audit-log — paginated, filtered audit log.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);
        $page = max((int) $request->query('page', 1), 1);

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

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $entries = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'entries' => $entries->map(fn (AuditLog $e) => [
                    'id' => $e->id,
                    'timestamp' => $e->created_at?->toISOString(),
                    'event_type' => $e->event_type ?? $e->action,
                    'user_id' => $e->user_id,
                    'username' => $e->username,
                    'target_type' => $e->target_type,
                    'target_id' => $e->target_id,
                    'ip_address' => $e->ip_address,
                    'details' => is_array($e->details) ? json_encode($e->details) : $e->details,
                ]),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/audit-log/export — CSV export of audit log.
     */
    public function export(Request $request): StreamedResponse
    {
        $entries = $this->filteredQuery($request)->get();

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Timestamp', 'Event Type', 'User ID', 'Username', 'Target Type', 'Target ID', 'IP Address', 'Details']);

            foreach ($entries as $e) {
                fputcsv($out, [
                    $e->created_at?->toISOString(),
                    $e->event_type ?? $e->action,
                    $e->user_id,
                    $e->username,
                    $e->target_type,
                    $e->target_id,
                    $e->ip_address,
                    $e->details,
                ]);
            }

            fclose($out);
        }, 'audit-log.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * GET /api/v1/admin/audit-log/export/enhanced — multi-format export (csv/json/pdf).
     * Requires MODULE_AUDIT_EXPORT=true.
     */
    public function exportEnhanced(Request $request): JsonResponse|StreamedResponse
    {
        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'json', 'pdf'])) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_FORMAT', 'message' => 'Format must be csv, json, or pdf'],
            ], 422);
        }

        $query = AuditLog::query()->orderByDesc('created_at');

        if ($action = $request->query('action')) {
            $query->where(function ($q) use ($action) {
                $q->where('event_type', $action)->orWhere('action', $action);
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

        $entries = $query->get();

        $mapped = $entries->map(fn (AuditLog $e) => [
            'id' => $e->id,
            'timestamp' => $e->created_at?->toISOString(),
            'event_type' => $e->event_type ?? $e->action,
            'user_id' => $e->user_id,
            'username' => $e->username,
            'target_type' => $e->target_type,
            'target_id' => $e->target_id,
            'ip_address' => $e->ip_address,
            'details' => is_array($e->details) ? json_encode($e->details) : $e->details,
        ])->values();

        if ($format === 'json') {
            return response()->streamDownload(function () use ($mapped) {
                echo json_encode([
                    'exported_at' => now()->toISOString(),
                    'count' => $mapped->count(),
                    'entries' => $mapped,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }, 'audit-log.json', [
                'Content-Type' => 'application/json',
            ]);
        }

        if ($format === 'pdf') {
            // Return a simplified text/plain representation for PDF
            // (full PDF rendering requires a library like dompdf — keep it lightweight)
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

        // CSV (default)
        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Timestamp', 'Event Type', 'User ID', 'Username', 'Target Type', 'Target ID', 'IP Address', 'Details']);

            foreach ($entries as $e) {
                fputcsv($out, [
                    $e->created_at?->toISOString(),
                    $e->event_type ?? $e->action,
                    $e->user_id,
                    $e->username,
                    $e->target_type,
                    $e->target_id,
                    $e->ip_address,
                    is_array($e->details) ? json_encode($e->details) : $e->details,
                ]);
            }

            fclose($out);
        }, 'audit-log.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Build a filtered audit log query from request params.
     */
    private function filteredQuery(Request $request): Builder
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

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        return $query;
    }
}
