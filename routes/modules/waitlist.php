<?php

/**
 * Enhanced Waitlist module routes (api/v1).
 * Loaded only when MODULE_WAITLIST_EXT=true.
 */

use App\Http\Controllers\Api\WaitlistController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:waitlist_ext', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/lots/{lotId}/waitlist/subscribe', [WaitlistController::class, 'subscribe']);
    Route::get('/lots/{lotId}/waitlist', [WaitlistController::class, 'lotWaitlist']);
    Route::delete('/lots/{lotId}/waitlist', [WaitlistController::class, 'leave']);
    Route::post('/lots/{lotId}/waitlist/{entryId}/accept', [WaitlistController::class, 'accept']);
    Route::post('/lots/{lotId}/waitlist/{entryId}/decline', [WaitlistController::class, 'decline']);
});
