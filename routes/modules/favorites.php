<?php

/**
 * Favorites module routes (api/v1).
 * Loaded only when MODULE_FAVORITES=true.
 */

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:favorites', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/user/favorites', [UserController::class, 'favorites']);
    Route::post('/user/favorites', [UserController::class, 'addFavorite']);
    Route::delete('/user/favorites/{slotId}', [UserController::class, 'removeFavorite']);
});
