<?php

/**
 * GraphQL API module routes (api/v1).
 * Loaded only when MODULE_GRAPHQL=true.
 */

use App\Http\Controllers\Api\GraphQLController;
use Illuminate\Support\Facades\Route;

// Playground is public (authentication happens inside the GraphQL query via bearer token)
Route::middleware(['module:graphql'])->group(function () {
    Route::get('/graphql/playground', [GraphQLController::class, 'playground']);
});

Route::middleware(['module:graphql', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/graphql', [GraphQLController::class, 'handle']);
});
