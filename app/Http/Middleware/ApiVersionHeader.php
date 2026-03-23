<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add X-API-Version response header and validate client version header.
 *
 * If the client sends an X-API-Version header that doesn't match a supported
 * version, a warning header is returned but the request is not rejected.
 */
class ApiVersionHeader
{
    private const CURRENT_VERSION = '1';

    private const SUPPORTED_VERSIONS = ['1'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Always stamp the response with the current API version
        $response->headers->set('X-API-Version', self::CURRENT_VERSION);

        // If the client specified a version, validate it
        $clientVersion = $request->header('X-API-Version');
        if ($clientVersion && ! in_array($clientVersion, self::SUPPORTED_VERSIONS, true)) {
            $response->headers->set(
                'X-API-Version-Warning',
                "Requested API version '{$clientVersion}' is not supported. Using v".self::CURRENT_VERSION
            );
        }

        return $response;
    }
}
