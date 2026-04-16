<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared helpers for tenant-aware query plumbing.
 *
 * Eloquent models pick up tenant isolation automatically via the
 * `App\Models\Concerns\BelongsToTenant` trait + `BelongsToTenantScope`
 * global scope. Raw `DB::table(...)` callsites — LobbyDisplayController,
 * UserController guest-bookings cleanup, MetricsController active-
 * session counts, etc. — can't inherit that, so they read the current
 * tenant via `TenantScope::currentId()` and apply a `->where('tenant_id',
 * ...)` when the feature flag is on.
 *
 * When `MODULE_MULTI_TENANT` is off, `currentId()` always returns null
 * and callers should no-op their tenant-WHERE; when it's on and no
 * tenant is bound (pre-auth or artisan path), it also returns null and
 * the caller should decide whether an unscoped query is actually
 * appropriate.
 */
final class TenantScope
{
    /**
     * Current tenant id, or null if multi-tenant is disabled / no tenant
     * is bound in the container. Accepts both Eloquent Tenant models and
     * the stdClass stubs used in tests.
     */
    public static function currentId(): ?string
    {
        if (! config('modules.multi_tenant')) {
            return null;
        }

        if (! app()->bound('current_tenant')) {
            return null;
        }

        $tenant = app('current_tenant');

        return match (true) {
            is_object($tenant) && method_exists($tenant, 'getKey') => (string) $tenant->getKey(),
            is_object($tenant) && property_exists($tenant, 'id') => (string) $tenant->id,
            default => null,
        };
    }

    /**
     * Convenience wrapper: apply `->where("$qualifier.tenant_id", ...)`
     * to a query builder when a tenant is currently bound. Returns the
     * builder unchanged otherwise so callers can chain unconditionally.
     *
     * @template TBuilder of \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function applyTo($query, string $qualifier = '')
    {
        $tenantId = self::currentId();
        if ($tenantId === null) {
            return $query;
        }

        $column = $qualifier === '' ? 'tenant_id' : "{$qualifier}.tenant_id";

        return $query->where($column, $tenantId);
    }
}
