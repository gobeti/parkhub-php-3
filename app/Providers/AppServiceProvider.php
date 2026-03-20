<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Define named rate limiters for sensitive endpoints.
     */
    private function configureRateLimiting(): void
    {
        // Auth endpoints: 5 attempts per minute per IP (brute-force protection)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Password reset: 3 attempts per 15 minutes per IP
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinutes(15, 3)->by($request->ip());
        });

        // Demo reset: 2 per minute per IP (heavy DB operation)
        RateLimiter::for('demo-action', function (Request $request) {
            return Limit::perMinute(2)->by($request->ip());
        });

        // Setup mutations: 3 per minute per IP
        RateLimiter::for('setup', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Authenticated API: 120 per minute per user (or IP if unauthenticated)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
