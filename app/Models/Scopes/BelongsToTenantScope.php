<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that limits a model's queries to the authenticated user's
 * tenant when `config('modules.multi_tenant')` is true.
 *
 * No-op when multi-tenant mode is disabled, so single-tenant deployments
 * and our current default behaviour stay exactly as they were.
 *
 * The scope only kicks in when a tenant is resolved in the container
 * (set by `App\Http\Middleware\TenantScope`). If no tenant is bound —
 * e.g. an artisan command or a pre-auth route — the scope also no-ops
 * because the caller is outside the multi-tenant boundary.
 */
class BelongsToTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! config('modules.multi_tenant')) {
            return;
        }

        if (! app()->bound('current_tenant')) {
            return;
        }

        $tenant = app('current_tenant');

        // Accept both Eloquent Tenant models and a plain stdClass stub
        // (handy for testing and for the TenantScope middleware path which
        // hands the hydrated model through). `getKey()` is the canonical
        // Eloquent accessor and falls back to the public `id` for stubs.
        $tenantId = match (true) {
            is_object($tenant) && method_exists($tenant, 'getKey') => $tenant->getKey(),
            is_object($tenant) && property_exists($tenant, 'id') => $tenant->id,
            default => null,
        };

        if ($tenantId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
