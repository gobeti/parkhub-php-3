<?php

/**
 * Admin Reports module routes (api/v1).
 * Loaded only when MODULE_ADMIN_REPORTS=true.
 */

use App\Http\Controllers\Api\AdminReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:admin_reports', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin')->group(function () {
    Route::get('/stats', [AdminReportController::class, 'stats']);
    Route::get('/heatmap', [AdminReportController::class, 'heatmap']);
    Route::get('/users/export-csv', [AdminReportController::class, 'exportUsersCsv']);
    Route::get('/reports', [AdminReportController::class, 'reports']);
    Route::get('/dashboard/charts', [AdminReportController::class, 'dashboardCharts']);
});
