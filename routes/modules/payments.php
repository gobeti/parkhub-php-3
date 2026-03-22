<?php

/**
 * Payments module routes (api/v1).
 * Loaded only when MODULE_PAYMENTS=true.
 */

use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

// Stripe webhook (no auth — Stripe signs the payload)
Route::middleware('module:payments')->group(function () {
    Route::post('/payments/webhook', [PaymentController::class, 'webhook']);
});

Route::middleware(['module:payments', 'auth:sanctum', 'throttle:payments'])->group(function () {
    Route::post('/payments/create-intent', [PaymentController::class, 'createIntent']);
    Route::post('/payments/confirm', [PaymentController::class, 'confirm']);
    Route::get('/payments/{id}/status', [PaymentController::class, 'status']);
});
