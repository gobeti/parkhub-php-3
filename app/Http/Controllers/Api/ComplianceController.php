<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Compliance\ComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function __construct(private readonly ComplianceService $service) {}

    /**
     * GET /admin/compliance/report — GDPR/DSGVO compliance status with 10 checks.
     */
    public function report(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->report(),
        ]);
    }

    /**
     * GET /admin/compliance/data-map — Art. 30 data processing inventory.
     */
    public function dataMap(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->dataMap(),
        ]);
    }

    /**
     * GET /admin/compliance/audit-export — audit trail CSV or JSON.
     */
    public function auditExport(Request $request): JsonResponse
    {
        $format = $request->query('format', 'json');
        $limit = min((int) $request->query('limit', 1000), 5000);

        $logs = $this->service->auditLogs($limit);

        if ($logs === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'format' => $format,
                    'logs' => [],
                    'count' => 0,
                    'exported_at' => now()->toISOString(),
                    'content' => $format === 'csv' ? "id,user_id,action,resource_type,resource_id,ip_address,created_at\n" : null,
                ],
            ]);
        }

        if ($format === 'csv') {
            return response()->json([
                'success' => true,
                'data' => [
                    'format' => 'csv',
                    'content' => $this->service->auditLogsCsv($logs),
                    'count' => count($logs),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'format' => 'json',
                'logs' => $logs,
                'count' => count($logs),
                'exported_at' => now()->toISOString(),
            ],
        ]);
    }
}
