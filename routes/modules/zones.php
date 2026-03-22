<?php

/**
 * Zones module routes (api/v1).
 * Loaded only when MODULE_ZONES=true.
 */

use App\Http\Controllers\Api\ZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:zones', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/lots/{lotId}/zones', [ZoneController::class, 'index']);

    // Zone mutations require admin privileges
    Route::middleware('admin')->group(function () {
        Route::post('/lots/{lotId}/zones', [ZoneController::class, 'store']);
        Route::put('/lots/{lotId}/zones/{id}', [ZoneController::class, 'update']);
        Route::delete('/lots/{lotId}/zones/{id}', [ZoneController::class, 'destroy']);
    });
});
