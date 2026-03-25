<?php

/**
 * Mobile Booking module routes (api/v1).
 * Loaded when MODULE_MOBILE=true.
 *
 * Provides mobile-optimized booking endpoints: nearby lots discovery,
 * quick booking, and active booking countdown.
 */

use App\Http\Controllers\Api\MobileBookingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:mobile', 'auth:sanctum', 'throttle:api'])->prefix('mobile')->group(function () {
    Route::get('/nearby-lots', [MobileBookingController::class, 'nearbyLots']);
    Route::get('/quick-book', [MobileBookingController::class, 'quickBook']);
    Route::get('/active-booking', [MobileBookingController::class, 'activeBooking']);
});
