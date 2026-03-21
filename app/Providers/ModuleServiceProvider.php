<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Conditionally registers per-module route files based on config/modules.php.
 *
 * When a module is disabled its routes are never registered, so the
 * framework returns 404 naturally — no extra middleware needed for
 * routes that live inside a module file.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('modules.php'), 'modules');
    }

    public function boot(): void
    {
        // Module route files are loaded by the route files themselves
        // (api.php and api_v1.php) using the module_routes() helper.
    }

    /**
     * Check whether a module is enabled.
     */
    public static function enabled(string $module): bool
    {
        return (bool) config("modules.{$module}", false);
    }

    /**
     * Return all modules with their enabled/disabled state.
     */
    public static function all(): array
    {
        return array_map(fn ($v) => (bool) $v, config('modules', []));
    }
}
