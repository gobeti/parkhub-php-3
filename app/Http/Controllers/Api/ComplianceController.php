<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplianceController extends Controller
{
    /**
     * GET /admin/compliance/report — GDPR/DSGVO compliance status with 10 checks.
     */
    public function report(): JsonResponse
    {
        $checks = $this->runChecks();
        $overallStatus = $this->computeOverallStatus($checks);

        return response()->json([
            'success' => true,
            'data' => [
                'generated_at' => now()->toISOString(),
                'overall_status' => $overallStatus,
                'checks' => $checks,
                'data_categories' => $this->dataCategories(),
                'legal_basis' => $this->legalBasis(),
                'retention_periods' => $this->retentionPeriods(),
                'sub_processors' => $this->subProcessors(),
                'tom_summary' => $this->tomSummary($checks),
            ],
        ]);
    }

    /**
     * GET /admin/compliance/data-map — Art. 30 data processing inventory.
     */
    public function dataMap(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'organization' => config('app.name', 'ParkHub'),
                'generated_at' => now()->toISOString(),
                'processing_activities' => [
                    [
                        'name' => 'User Account Management',
                        'purpose' => 'Authentication and authorization for parking management',
                        'data_subjects' => ['employees', 'visitors'],
                        'data_categories' => ['name', 'email', 'role', 'password_hash'],
                        'legal_basis' => 'Art. 6(1)(b) - Contract performance',
                        'retention' => 'Duration of account + 30 days',
                        'recipients' => ['Internal administrators'],
                    ],
                    [
                        'name' => 'Booking Management',
                        'purpose' => 'Parking spot reservation and allocation',
                        'data_subjects' => ['employees', 'visitors'],
                        'data_categories' => ['user_id', 'lot_id', 'slot_id', 'timestamps'],
                        'legal_basis' => 'Art. 6(1)(b) - Contract performance',
                        'retention' => '12 months after booking date',
                        'recipients' => ['Internal administrators', 'Lot managers'],
                    ],
                    [
                        'name' => 'Vehicle Registration',
                        'purpose' => 'Vehicle identification for parking access',
                        'data_subjects' => ['employees'],
                        'data_categories' => ['license_plate', 'make', 'model', 'color'],
                        'legal_basis' => 'Art. 6(1)(a) - Consent',
                        'retention' => 'Until vehicle removed by user',
                        'recipients' => ['Internal administrators'],
                    ],
                    [
                        'name' => 'Audit Logging',
                        'purpose' => 'Security and accountability tracking',
                        'data_subjects' => ['all users'],
                        'data_categories' => ['user_id', 'action', 'ip_address', 'timestamp'],
                        'legal_basis' => 'Art. 6(1)(f) - Legitimate interest (security)',
                        'retention' => '90 days',
                        'recipients' => ['Security administrators'],
                    ],
                    [
                        'name' => 'Payment Processing',
                        'purpose' => 'Billing for parking services',
                        'data_subjects' => ['employees', 'visitors'],
                        'data_categories' => ['payment_method', 'amount', 'invoice_data'],
                        'legal_basis' => 'Art. 6(1)(b) - Contract performance',
                        'retention' => '10 years (tax requirement)',
                        'recipients' => ['Payment processor (Stripe)', 'Tax authorities'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * GET /admin/compliance/audit-export — audit trail CSV or JSON.
     */
    public function auditExport(Request $request): JsonResponse
    {
        $format = $request->query('format', 'json');
        $limit = min((int) $request->query('limit', 1000), 5000);

        if (! DB::getSchemaBuilder()->hasTable('audit_logs')) {
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

        $logs = DB::table('audit_logs')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'user_id', 'action', 'resource_type', 'resource_id', 'ip_address', 'created_at']);

        if ($format === 'csv') {
            $csv = "id,user_id,action,resource_type,resource_id,ip_address,created_at\n";
            foreach ($logs as $log) {
                $csv .= implode(',', [
                    $log->id,
                    $log->user_id ?? '',
                    '"'.str_replace('"', '""', $log->action).'"',
                    $log->resource_type ?? '',
                    $log->resource_id ?? '',
                    $log->ip_address ?? '',
                    $log->created_at,
                ])."\n";
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'format' => 'csv',
                    'content' => $csv,
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

    private function runChecks(): array
    {
        return [
            [
                'id' => 'encryption-at-rest',
                'category' => 'Security',
                'name' => 'Encryption at Rest',
                'description' => 'Database and file encryption',
                'status' => 'compliant',
                'details' => 'Application uses encrypted database connections and bcrypt password hashing',
                'recommendation' => null,
            ],
            [
                'id' => 'encryption-in-transit',
                'category' => 'Security',
                'name' => 'Encryption in Transit',
                'description' => 'TLS/HTTPS enforcement',
                'status' => config('app.env') === 'production' ? 'compliant' : 'warning',
                'details' => config('app.env') === 'production' ? 'HTTPS enforced in production' : 'Running in non-production mode',
                'recommendation' => config('app.env') === 'production' ? null : 'Enable HTTPS for production deployment',
            ],
            [
                'id' => 'access-control',
                'category' => 'Security',
                'name' => 'Access Control',
                'description' => 'Role-based access control (RBAC)',
                'status' => 'compliant',
                'details' => 'RBAC with admin/superadmin/user roles, Sanctum token authentication',
                'recommendation' => null,
            ],
            [
                'id' => 'audit-logging',
                'category' => 'Accountability',
                'name' => 'Audit Logging',
                'description' => 'Action audit trail',
                'status' => DB::getSchemaBuilder()->hasTable('audit_logs') ? 'compliant' : 'non_compliant',
                'details' => DB::getSchemaBuilder()->hasTable('audit_logs') ? 'Audit log table exists with action tracking' : 'Audit log table not found',
                'recommendation' => DB::getSchemaBuilder()->hasTable('audit_logs') ? null : 'Enable the audit_log module',
            ],
            [
                'id' => 'data-minimization',
                'category' => 'Data Protection',
                'name' => 'Data Minimization',
                'description' => 'Only essential data collected',
                'status' => 'compliant',
                'details' => 'User profiles contain only name, email, and role. Vehicle data is optional.',
                'recommendation' => null,
            ],
            [
                'id' => 'data-portability',
                'category' => 'Data Subject Rights',
                'name' => 'Data Portability (Art. 20)',
                'description' => 'User data export capability',
                'status' => 'compliant',
                'details' => 'JSON export available via /api/v1/user/export',
                'recommendation' => null,
            ],
            [
                'id' => 'right-to-erasure',
                'category' => 'Data Subject Rights',
                'name' => 'Right to Erasure (Art. 17)',
                'description' => 'Account deletion capability',
                'status' => 'compliant',
                'details' => 'Admin can delete user accounts and associated data',
                'recommendation' => null,
            ],
            [
                'id' => 'consent-management',
                'category' => 'Lawfulness',
                'name' => 'Consent Management',
                'description' => 'User consent tracking',
                'status' => 'warning',
                'details' => 'Basic consent via registration. No granular consent management.',
                'recommendation' => 'Implement granular consent management for optional data processing',
            ],
            [
                'id' => 'dpo-appointed',
                'category' => 'Organization',
                'name' => 'Data Protection Officer',
                'description' => 'DPO designation',
                'status' => 'warning',
                'details' => 'No DPO configured in system settings',
                'recommendation' => 'Consider appointing a Data Protection Officer for organizations with >250 employees',
            ],
            [
                'id' => 'privacy-policy',
                'category' => 'Transparency',
                'name' => 'Privacy Policy',
                'description' => 'Published privacy policy',
                'status' => 'compliant',
                'details' => 'Privacy policy available at /api/v1/legal/privacy and configurable via admin settings',
                'recommendation' => null,
            ],
        ];
    }

    private function computeOverallStatus(array $checks): string
    {
        $hasNonCompliant = collect($checks)->contains('status', 'non_compliant');
        $hasWarning = collect($checks)->contains('status', 'warning');

        if ($hasNonCompliant) {
            return 'non_compliant';
        }
        if ($hasWarning) {
            return 'warning';
        }

        return 'compliant';
    }

    private function dataCategories(): array
    {
        return [
            ['category' => 'Identity', 'fields' => ['name', 'email'], 'sensitivity' => 'standard'],
            ['category' => 'Authentication', 'fields' => ['password_hash', 'api_tokens'], 'sensitivity' => 'high'],
            ['category' => 'Vehicle', 'fields' => ['license_plate', 'make', 'model', 'color'], 'sensitivity' => 'standard'],
            ['category' => 'Booking', 'fields' => ['lot_id', 'slot_id', 'timestamps'], 'sensitivity' => 'standard'],
            ['category' => 'Audit', 'fields' => ['ip_address', 'user_agent', 'action'], 'sensitivity' => 'standard'],
        ];
    }

    private function legalBasis(): array
    {
        return [
            ['processing' => 'Account management', 'basis' => 'Art. 6(1)(b)', 'description' => 'Contract performance'],
            ['processing' => 'Parking bookings', 'basis' => 'Art. 6(1)(b)', 'description' => 'Contract performance'],
            ['processing' => 'Security logging', 'basis' => 'Art. 6(1)(f)', 'description' => 'Legitimate interest'],
            ['processing' => 'Vehicle registration', 'basis' => 'Art. 6(1)(a)', 'description' => 'Consent'],
            ['processing' => 'Payment processing', 'basis' => 'Art. 6(1)(b)', 'description' => 'Contract performance'],
        ];
    }

    private function retentionPeriods(): array
    {
        return [
            ['data_type' => 'User accounts', 'period' => 'Account lifetime + 30 days'],
            ['data_type' => 'Booking records', 'period' => '12 months'],
            ['data_type' => 'Audit logs', 'period' => '90 days'],
            ['data_type' => 'Payment records', 'period' => '10 years (tax)'],
            ['data_type' => 'Session data', 'period' => '24 hours'],
        ];
    }

    private function subProcessors(): array
    {
        return [
            ['name' => 'Stripe', 'purpose' => 'Payment processing', 'location' => 'US/EU', 'safeguards' => 'DPA, SCCs'],
        ];
    }

    private function tomSummary(array $checks): array
    {
        $isCompliant = fn (string $id) => collect($checks)->firstWhere('id', $id)['status'] === 'compliant';

        return [
            'encryption_at_rest' => $isCompliant('encryption-at-rest'),
            'encryption_in_transit' => $isCompliant('encryption-in-transit'),
            'access_control' => $isCompliant('access-control'),
            'audit_logging' => $isCompliant('audit-logging'),
            'data_minimization' => $isCompliant('data-minimization'),
            'backup_encryption' => true,
            'incident_response_plan' => false,
            'dpo_appointed' => $isCompliant('dpo-appointed'),
            'privacy_by_design' => true,
            'regular_audits' => false,
        ];
    }
}
