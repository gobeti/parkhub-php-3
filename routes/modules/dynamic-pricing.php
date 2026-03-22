<?php

/**
 * Dynamic Pricing module routes (api/v1).
 * Loaded only when MODULE_DYNAMIC_PRICING=true.
 */

use App\Http\Controllers\Api\DynamicPricingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:dynamic_pricing', 'auth:sanctum', 'throttle:api'])->group(function () {
    // Public (authenticated) — get current dynamic price
    Route::get('/lots/{id}/pricing/dynamic', [DynamicPricingController::class, 'show']);

    // Admin — manage pricing rules
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/lots/{id}/pricing/dynamic', [DynamicPricingController::class, 'adminShow']);
        Route::put('/lots/{id}/pricing/dynamic', [DynamicPricingController::class, 'adminUpdate']);
    });
});
