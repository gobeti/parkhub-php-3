<?php

/**
 * Map module routes (api/v1).
 * Loaded only when MODULE_MAP=true.
 */

use App\Http\Controllers\Api\MapController;
use Illuminate\Support\Facades\Route;

// Public map endpoint (no auth — same as lobby display)
Route::middleware('module:map')->group(function () {
    Route::get('/lots/map', [MapController::class, 'index']);
});

// Admin: set lot coordinates
Route::middleware(['module:map', 'auth:sanctum', 'throttle:api', 'admin'])->group(function () {
    Route::put('/admin/lots/{id}/location', [MapController::class, 'setLocation']);
});
