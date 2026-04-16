<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scope requests to the user's tenant when multi-tenant is enabled.
 *
 * Resolves tenant from the authenticated user's tenant_id.
 * When MODULE_MULTI_TENANT is disabled, this middleware is a no-op.
 */
class TenantScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('modules.multi_tenant')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);

            if ($tenant) {
                app()->instance('current_tenant', $tenant);
            }
        }

        return $next($request);
    }
}
