<?php

use App\Http\Middleware\ApiResponseWrapper;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Security headers on every response (web + API)
        $middleware->append(SecurityHeaders::class);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            ApiResponseWrapper::class,
        ]);

        $middleware->alias(['admin' => RequireAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
