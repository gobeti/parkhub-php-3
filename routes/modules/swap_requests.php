<?php

/**
 * Swap Requests module routes (api/v1).
 * Loaded only when MODULE_SWAP_REQUESTS=true.
 * Depends on: bookings module.
 */

use App\Http\Controllers\Api\BookingSwapController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:swap_requests', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/bookings/swap', [BookingSwapController::class, 'swap']);
    Route::get('/swap-requests', [BookingSwapController::class, 'swapRequests']);
    Route::post('/bookings/{id}/swap-request', [BookingSwapController::class, 'createSwapRequest']);
    Route::put('/swap-requests/{id}', [BookingSwapController::class, 'respondSwapRequest']);
});
