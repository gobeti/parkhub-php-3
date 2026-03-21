<?php

/**
 * Metrics module routes (api/v1).
 * Loaded only when MODULE_METRICS=true.
 */

use App\Http\Controllers\Api\PulseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:metrics', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin')->group(function () {
    Route::get('/pulse', [PulseController::class, 'index']);
});
