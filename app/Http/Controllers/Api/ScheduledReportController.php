<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScheduledReportController extends Controller
{
    /**
     * In-memory schedule store (production would use a DB table).
     */
    private static array $schedules = [];

    private static array $validReportTypes = [
        'occupancy_summary',
        'revenue_report',
        'user_activity',
        'booking_trends',
    ];

    private static array $validFrequencies = [
        'daily',
        'weekly',
        'monthly',
    ];

    /**
     * GET /admin/reports/schedules — list all report schedules.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'schedules' => array_values(self::$schedules),
                'total' => count(self::$schedules),
            ],
        ]);
    }

    /**
     * POST /admin/reports/schedules — create a new schedule.
     */
    public function store(Request $request): JsonResponse
    {
        $name = $request->input('name');
        $reportType = $request->input('report_type');
        $frequency = $request->input('frequency');
        $recipients = $request->input('recipients', []);

        if (! $name || ! is_string($name) || trim($name) === '') {
            return response()->json([
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Name is required',
            ], 422);
        }

        if (! in_array($reportType, self::$validReportTypes, true)) {
            return response()->json([
                'success' => false,
                'error' => 'INVALID_REPORT_TYPE',
                'message' => 'Invalid report type. Allowed: '.implode(', ', self::$validReportTypes),
            ], 422);
        }

        if (! in_array($frequency, self::$validFrequencies, true)) {
            return response()->json([
                'success' => false,
                'error' => 'INVALID_FREQUENCY',
                'message' => 'Invalid frequency. Allowed: '.implode(', ', self::$validFrequencies),
            ], 422);
        }

        if (! is_array($recipients) || count($recipients) === 0) {
            return response()->json([
                'success' => false,
                'error' => 'RECIPIENTS_REQUIRED',
                'message' => 'At least one recipient email is required',
            ], 422);
        }

        $id = 'sched-'.Str::uuid();
        $now = now();

        $nextRun = match ($frequency) {
            'daily' => $now->copy()->addDay()->setTime(8, 0)->toIso8601String(),
            'weekly' => $now->copy()->next('Monday')->setTime(8, 0)->toIso8601String(),
            'monthly' => $now->copy()->addMonth()->startOfMonth()->setTime(8, 0)->toIso8601String(),
        };

        $schedule = [
            'id' => $id,
            'name' => trim($name),
            'report_type' => $reportType,
            'frequency' => $frequency,
            'recipients' => $recipients,
            'enabled' => true,
            'last_sent_at' => null,
            'next_run_at' => $nextRun,
            'created_at' => $now->toIso8601String(),
            'updated_at' => $now->toIso8601String(),
        ];

        self::$schedules[$id] = $schedule;

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ], 201);
    }

    /**
     * GET /admin/reports/schedules/{id} — show a single schedule.
     */
    public function show(string $id): JsonResponse
    {
        if (! isset(self::$schedules[$id])) {
            return response()->json([
                'success' => false,
                'error' => 'SCHEDULE_NOT_FOUND',
                'message' => "Schedule '{$id}' not found",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => self::$schedules[$id],
        ]);
    }

    /**
     * PUT /admin/reports/schedules/{id} — update a schedule.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if (! isset(self::$schedules[$id])) {
            return response()->json([
                'success' => false,
                'error' => 'SCHEDULE_NOT_FOUND',
                'message' => "Schedule '{$id}' not found",
            ], 404);
        }

        $schedule = self::$schedules[$id];

        if ($request->has('name')) {
            $name = $request->input('name');
            if (! is_string($name) || trim($name) === '') {
                return response()->json([
                    'success' => false,
                    'error' => 'VALIDATION_ERROR',
                    'message' => 'Name cannot be empty',
                ], 422);
            }
            $schedule['name'] = trim($name);
        }

        if ($request->has('report_type')) {
            $rt = $request->input('report_type');
            if (! in_array($rt, self::$validReportTypes, true)) {
                return response()->json([
                    'success' => false,
                    'error' => 'INVALID_REPORT_TYPE',
                    'message' => 'Invalid report type',
                ], 422);
            }
            $schedule['report_type'] = $rt;
        }

        if ($request->has('frequency')) {
            $freq = $request->input('frequency');
            if (! in_array($freq, self::$validFrequencies, true)) {
                return response()->json([
                    'success' => false,
                    'error' => 'INVALID_FREQUENCY',
                    'message' => 'Invalid frequency',
                ], 422);
            }
            $schedule['frequency'] = $freq;
        }

        if ($request->has('recipients')) {
            $recip = $request->input('recipients');
            if (! is_array($recip) || count($recip) === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'RECIPIENTS_REQUIRED',
                    'message' => 'At least one recipient required',
                ], 422);
            }
            $schedule['recipients'] = $recip;
        }

        if ($request->has('enabled')) {
            $schedule['enabled'] = (bool) $request->input('enabled');
        }

        $schedule['updated_at'] = now()->toIso8601String();

        self::$schedules[$id] = $schedule;

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }

    /**
     * DELETE /admin/reports/schedules/{id} — delete a schedule.
     */
    public function destroy(string $id): JsonResponse
    {
        if (! isset(self::$schedules[$id])) {
            return response()->json([
                'success' => false,
                'error' => 'SCHEDULE_NOT_FOUND',
                'message' => "Schedule '{$id}' not found",
            ], 404);
        }

        unset(self::$schedules[$id]);

        return response()->json([
            'success' => true,
            'data' => ['deleted' => true],
        ]);
    }

    /**
     * POST /admin/reports/schedules/{id}/send-now — trigger immediate report delivery.
     */
    public function sendNow(string $id): JsonResponse
    {
        if (! isset(self::$schedules[$id])) {
            return response()->json([
                'success' => false,
                'error' => 'SCHEDULE_NOT_FOUND',
                'message' => "Schedule '{$id}' not found",
            ], 404);
        }

        $schedule = self::$schedules[$id];
        $schedule['last_sent_at'] = now()->toIso8601String();
        self::$schedules[$id] = $schedule;

        return response()->json([
            'success' => true,
            'data' => [
                'schedule_id' => $id,
                'sent_to' => $schedule['recipients'],
                'report_type' => $schedule['report_type'],
                'sent_at' => $schedule['last_sent_at'],
            ],
        ]);
    }
}
