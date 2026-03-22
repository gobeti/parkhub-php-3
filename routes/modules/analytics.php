<?php

/**
 * Analytics module routes (api/v1).
 * Loaded only when MODULE_ANALYTICS=true.
 */

use App\Http\Controllers\Api\AdminAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:analytics', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin/analytics')->group(function () {
    Route::get('/overview', [AdminAnalyticsController::class, 'overview']);
});
