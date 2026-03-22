<?php

/**
 * Operating Hours module routes (api/v1).
 * Loaded only when MODULE_OPERATING_HOURS=true.
 */

use App\Http\Controllers\Api\OperatingHoursController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:operating_hours', 'auth:sanctum', 'throttle:api'])->group(function () {
    // Public (authenticated) — get operating hours
    Route::get('/lots/{id}/hours', [OperatingHoursController::class, 'show']);

    // Admin — set operating hours
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::put('/lots/{id}/hours', [OperatingHoursController::class, 'update']);
    });
});
