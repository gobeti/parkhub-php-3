<?php

/**
 * Credits module routes (api/v1).
 * Loaded only when MODULE_CREDITS=true.
 */

use App\Http\Controllers\Api\AdminCreditController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:credits', 'auth:sanctum', 'throttle:api'])->group(function () {
    // User credits
    Route::get('/user/credits', [UserController::class, 'credits']);

    // Admin credits management
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::put('/users/{id}/quota', [AdminCreditController::class, 'updateUserQuota']);
        Route::post('/users/{id}/credits', [AdminCreditController::class, 'grantCredits']);
        Route::get('/credits/transactions', [AdminCreditController::class, 'creditTransactions']);
        Route::post('/credits/refill-all', [AdminCreditController::class, 'refillAllCredits']);
    });
});
