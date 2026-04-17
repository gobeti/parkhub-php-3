<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogQueryService $auditLogs) {}

    /**
     * GET /api/v1/admin/audit-log — paginated, filtered audit log.
     *
     * @scramble-return array{
     *     success: bool,
     *     data: array{
     *         entries: array<int, array{
     *             id: string,
     *             timestamp: string,
     *             event_type: string|null,
     *             user_id: string|null,
     *             username: string|null,
     *             target_type: string|null,
     *             target_id: string|null,
     *             ip_address: string|null,
     *             details: string|object|array<int, mixed>|null,
     *         }>,
     *         total: int,
     *         page: int,
     *         per_page: int,
     *         total_pages: int,
     *     }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->auditLogs->paginate($request),
        ]);
    }

    /**
     * GET /api/v1/admin/audit-log/export — CSV export of audit log.
     */
    public function export(Request $request): StreamedResponse
    {
        return $this->auditLogs->streamCsv($request);
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

        return match ($format) {
            'json' => $this->auditLogs->streamJson($request),
            'pdf' => $this->auditLogs->streamPdf($request),
            default => $this->auditLogs->streamCsv($request),
        };
    }
}
