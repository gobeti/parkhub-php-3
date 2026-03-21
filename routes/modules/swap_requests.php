<?php

/**
 * Swap Requests module routes (api/v1).
 * Loaded only when MODULE_SWAP_REQUESTS=true.
 * Depends on: bookings module.
 */

use App\Http\Controllers\Api\BookingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:swap_requests', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/bookings/swap', [BookingController::class, 'swap']);
    Route::get('/swap-requests', [BookingController::class, 'swapRequests']);
    Route::post('/bookings/{id}/swap-request', [BookingController::class, 'createSwapRequest']);
    Route::put('/swap-requests/{id}', [BookingController::class, 'respondSwapRequest']);
});
