<?php

/**
 * Parking Zones module routes (api/v1).
 * Loaded only when MODULE_PARKING_ZONES=true.
 */

use App\Http\Controllers\Api\ParkingZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:parking_zones', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/lots/{lotId}/zones/pricing', [ParkingZoneController::class, 'index']);

    Route::middleware('admin')->group(function () {
        Route::post('/lots/{lotId}/zones/pricing', [ParkingZoneController::class, 'store']);
        Route::put('/admin/zones/{id}/pricing', [ParkingZoneController::class, 'updatePricing']);
        Route::delete('/lots/{lotId}/zones/{id}/pricing', [ParkingZoneController::class, 'destroyPricing']);
    });
});
