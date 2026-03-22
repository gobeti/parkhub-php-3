<?php

/**
 * Geofencing module routes (api/v1).
 * Loaded only when MODULE_GEOFENCE=true.
 */

use App\Http\Controllers\Api\GeofenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:geofence', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/geofence/check-in', [GeofenceController::class, 'checkIn']);
    Route::get('/lots/{id}/geofence', [GeofenceController::class, 'show']);

    Route::middleware('admin')->group(function () {
        Route::put('/admin/lots/{id}/geofence', [GeofenceController::class, 'update']);
    });
});
