<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\BelongsToTenantScope;

/**
 * Attach the `BelongsToTenantScope` global scope to an Eloquent model.
 *
 * The scope is gated at call-time on `config('modules.multi_tenant')`,
 * so applying this trait is a no-op in the default single-tenant build.
 * This keeps the wiring in place so enabling multi-tenancy later is a
 * flag flip instead of a refactor, without paying a per-query cost
 * today.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new BelongsToTenantScope);
    }
}
