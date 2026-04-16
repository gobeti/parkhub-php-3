<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject requests to routes whose module is disabled.
 *
 * Usage in route files:
 *   Route::middleware('module:bookings')->group(fn () => …);
 */
class CheckModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! config("modules.{$module}")) {
            return response()->json([
                'error' => 'MODULE_DISABLED',
                'message' => "The {$module} module is not enabled",
            ], 404);
        }

        return $next($request);
    }
}
