<?php

/**
 * Parking History module routes (api/v1).
 * Loaded only when MODULE_HISTORY=true.
 */

use App\Http\Controllers\Api\ParkingHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:history', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/bookings/history', [ParkingHistoryController::class, 'history']);
    Route::get('/bookings/stats', [ParkingHistoryController::class, 'stats']);
});
