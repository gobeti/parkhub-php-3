<?php

/**
 * Parking Pass module routes (api/v1).
 * Loaded only when MODULE_PARKING_PASS=true.
 */

use App\Http\Controllers\Api\ParkingPassController;
use Illuminate\Support\Facades\Route;

// Public verification endpoint (no auth required)
Route::middleware(['module:parking_pass', 'throttle:api'])->group(function () {
    Route::get('/pass/verify/{code}', [ParkingPassController::class, 'verify']);
});

// Protected pass endpoints
Route::middleware(['module:parking_pass', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/bookings/{id}/pass', [ParkingPassController::class, 'generate']);
    Route::get('/me/passes', [ParkingPassController::class, 'myPasses']);
});
