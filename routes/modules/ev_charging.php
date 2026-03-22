<?php

/**
 * EV Charging module routes (api/v1).
 * Loaded only when MODULE_EV_CHARGING=true.
 */

use App\Http\Controllers\Api\EVChargingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:ev_charging', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/lots/{id}/chargers', [EVChargingController::class, 'index']);
    Route::post('/chargers/{id}/start', [EVChargingController::class, 'start']);
    Route::post('/chargers/{id}/stop', [EVChargingController::class, 'stop']);
    Route::get('/chargers/sessions', [EVChargingController::class, 'sessions']);

    Route::middleware('admin')->group(function () {
        Route::get('/admin/chargers', [EVChargingController::class, 'adminIndex']);
        Route::post('/admin/chargers', [EVChargingController::class, 'store']);
    });
});
