<?php

/**
 * Rate Dashboard module routes (api/v1).
 * Loaded only when MODULE_RATE_DASHBOARD=true.
 */

use App\Http\Controllers\Api\RateDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:rate_dashboard', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin/rate-limits')->group(function () {
    Route::get('/', [RateDashboardController::class, 'index']);
    Route::get('/history', [RateDashboardController::class, 'history']);
});
