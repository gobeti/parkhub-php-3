<?php

/**
 * Payments module routes (api/v1).
 * Loaded only when MODULE_PAYMENTS=true.
 *
 * Note: Webhook, checkout, history and config routes are handled by
 * the Stripe module (routes/modules/stripe.php) when MODULE_STRIPE=true.
 */

use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:payments', 'auth:sanctum', 'throttle:payments'])->group(function () {
    Route::post('/payments/create-intent', [PaymentController::class, 'createIntent']);
    Route::post('/payments/confirm', [PaymentController::class, 'confirm']);
    Route::get('/payments/{id}/status', [PaymentController::class, 'status']);
});
