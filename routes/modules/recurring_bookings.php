<?php

/**
 * Recurring Bookings module routes (api/v1).
 * Loaded only when MODULE_RECURRING_BOOKINGS=true.
 * Depends on: bookings module.
 */

use App\Http\Controllers\Api\RecurringBookingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:recurring_bookings', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/recurring-bookings', [RecurringBookingController::class, 'index']);
    Route::post('/recurring-bookings', [RecurringBookingController::class, 'store']);
    Route::put('/recurring-bookings/{id}', [RecurringBookingController::class, 'update']);
    Route::delete('/recurring-bookings/{id}', [RecurringBookingController::class, 'destroy']);
});
