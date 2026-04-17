<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives browser-generated security reports.
 *
 * Two endpoints, both unauthenticated (browsers don't attach auth cookies
 * to beacon/Reporting-API POSTs) but rate limited at 10 req/min/IP:
 *
 * - POST /api/v1/security/csp-report — CSP violation reports
 * - POST /api/v1/security/nel-report — Network Error Logging reports
 *
 * Both endpoints:
 *   1. Validate that a body is present (the payload shape is browser-defined
 *      and changes between Chromium/Firefox/Safari versions — we can't bind
 *      to a FormRequest without losing forwards-compat, so we deliberately
 *      accept any JSON/beacon payload).
 *   2. Write the full payload to storage/logs/security-reports.log via the
 *      dedicated `security-reports` log channel (daily rotation, 30d).
 *   3. Drop a structured row into audit_log with action=csp_violation or
 *      action=nel_report so the audit-log UI can surface them.
 *
 * On success returns 204 No Content (standard for Reporting API endpoints).
 */
class SecurityReportController extends Controller
{
    public function cspReport(Request $request): Response
    {
        return $this->ingest($request, 'csp_violation');
    }

    public function nelReport(Request $request): Response
    {
        return $this->ingest($request, 'nel_report');
    }

    private function ingest(Request $request, string $action): Response
    {
        // We deliberately don't validate payload shape — Reporting API
        // payloads differ by browser + evolve every spec revision.
        // The only thing we require is that *something* was sent.
        // T-1749-intentional: browser-defined payload; FormRequest would over-constrain.
        $request->validate(['body' => 'present']);

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        Log::channel('security-reports')->warning($action, [
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
            'payload' => $payload,
        ]);

        AuditLog::log([
            'action' => $action,
            'event_type' => 'security_report',
            'details' => $payload,
            'ip_address' => $request->ip(),
        ]);

        return response()->noContent();
    }
}
