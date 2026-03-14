<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        // Force JSON responses from API routes.
        $request->headers->set('Accept', 'application/json');

        // Detect JSON request bodies even when Content-Type is missing or wrong.
        // Without this, Laravel's isJson() returns false and $request->all() won't
        // include fields from the JSON body, causing 422 "field is required" errors.
        if (! $request->isJson() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $content = $request->getContent();
            $firstChar = substr(ltrim($content), 0, 1);
            if ($content !== '' && ($firstChar === '{' || $firstChar === '[')) {
                json_decode($content);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->headers->set('Content-Type', 'application/json');
                }
            }
        }

        return $next($request);
    }
}
