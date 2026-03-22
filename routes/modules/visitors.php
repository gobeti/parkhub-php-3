<?php

/**
 * Visitor Pre-Registration module routes (api/v1).
 * Loaded only when MODULE_VISITORS=true.
 */

use App\Http\Controllers\Api\VisitorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:visitors', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/visitors/register', [VisitorController::class, 'register']);
    Route::get('/visitors', [VisitorController::class, 'index']);
    Route::put('/visitors/{id}/check-in', [VisitorController::class, 'checkIn']);
    Route::delete('/visitors/{id}', [VisitorController::class, 'destroy']);

    Route::middleware('admin')->group(function () {
        Route::get('/admin/visitors', [VisitorController::class, 'adminIndex']);
    });
});
