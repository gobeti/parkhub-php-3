<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ModuleGate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Unit-level checks of the `module` middleware alias.
 *
 * Covers both behaviours:
 *   - legacy fallback: slug isn't in the registry → config flag wins
 *     (mirrors the old CheckModule contract so existing routes stay
 *     compatible).
 *   - registry path: slug IS in the registry → `runtime_enabled`
 *     (which folds in Setting overrides) wins. Feature-level
 *     coverage of the Setting override lives in
 *     tests/Feature/Api/ModuleControllerTest.
 */
class ModuleGateTest extends TestCase
{
    private function runMiddleware(string $module): Response
    {
        $request = Request::create('/api/v1/test', 'GET');
        $middleware = new ModuleGate;

        return $middleware->handle($request, fn ($r) => response()->json(['ok' => true]), $module);
    }

    public function test_enabled_module_passes(): void
    {
        config(['modules.bookings' => true]);
        $response = $this->runMiddleware('bookings');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_disabled_module_returns_404(): void
    {
        config(['modules.fleet' => false]);
        $response = $this->runMiddleware('fleet');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_disabled_module_returns_module_disabled_error(): void
    {
        config(['modules.fleet' => false]);
        $response = $this->runMiddleware('fleet');
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('MODULE_DISABLED', $content['error'] ?? '');
    }

    public function test_unknown_module_defaults_to_disabled(): void
    {
        $response = $this->runMiddleware('nonexistent_module_xyz');
        $this->assertEquals(404, $response->getStatusCode());
    }
}
