<?php

/**
 * Stripe module routes (api/v1).
 * Loaded only when MODULE_STRIPE=true (disabled by default).
 *
 * Provides Stripe Checkout, webhook handling, payment history,
 * and configuration status endpoints.
 */

use App\Http\Controllers\Api\StripeController;
use Illuminate\Support\Facades\Route;

// Stripe webhook (no auth — Stripe signs the payload)
Route::middleware('module:stripe')->group(function () {
    Route::post('/payments/webhook', [StripeController::class, 'webhook']);
});

// Public: check if Stripe is configured
Route::middleware('module:stripe')->group(function () {
    Route::get('/payments/config/status', [StripeController::class, 'configStatus']);
});

// Authenticated Stripe endpoints
Route::middleware(['module:stripe', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/payments/create-checkout', [StripeController::class, 'createCheckout']);
    Route::get('/payments/history', [StripeController::class, 'history']);
});
