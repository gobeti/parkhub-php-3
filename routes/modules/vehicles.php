<?php

/**
 * Vehicles module routes (api/v1).
 * Loaded only when MODULE_VEHICLES=true.
 */

use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:vehicles', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::put('/vehicles/{id}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy']);
    Route::post('/vehicles/{id}/photo', [VehicleController::class, 'uploadPhoto']);
    Route::get('/vehicles/{id}/photo', [VehicleController::class, 'servePhoto']);
    Route::get('/vehicles/city-codes', [VehicleController::class, 'cityCodes']);
});
