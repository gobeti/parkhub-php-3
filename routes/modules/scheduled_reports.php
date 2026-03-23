<?php

/**
 * Scheduled Reports module routes (api/v1).
 * Loaded only when MODULE_SCHEDULED_REPORTS=true.
 */

use App\Http\Controllers\Api\ScheduledReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:scheduled_reports', 'auth:sanctum', 'throttle:api', 'admin'])->group(function () {
    Route::get('/admin/reports/schedules', [ScheduledReportController::class, 'index']);
    Route::post('/admin/reports/schedules', [ScheduledReportController::class, 'store']);
    Route::get('/admin/reports/schedules/{id}', [ScheduledReportController::class, 'show']);
    Route::put('/admin/reports/schedules/{id}', [ScheduledReportController::class, 'update']);
    Route::delete('/admin/reports/schedules/{id}', [ScheduledReportController::class, 'destroy']);
    Route::post('/admin/reports/schedules/{id}/send-now', [ScheduledReportController::class, 'sendNow']);
});
