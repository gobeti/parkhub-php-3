<?php

/**
 * Calendar Drag-to-Reschedule module routes (api/v1).
 * Loaded only when MODULE_CALENDAR_DRAG=true.
 */

use App\Http\Controllers\Api\RescheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:calendar_drag', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::put('/bookings/{id}/reschedule', [RescheduleController::class, 'reschedule']);
});
