<?php

namespace Tests\Unit\Providers;

use App\Providers\ModuleServiceProvider;
use Tests\TestCase;

class ModuleServiceProviderTest extends TestCase
{
    public function test_enabled_returns_true_for_enabled_module(): void
    {
        config(['modules.stripe' => true]);
        $this->assertTrue(ModuleServiceProvider::enabled('stripe'));
    }

    public function test_enabled_returns_false_for_disabled_module(): void
    {
        config(['modules.stripe' => false]);
        $this->assertFalse(ModuleServiceProvider::enabled('stripe'));
    }

    public function test_enabled_returns_false_for_unknown_module(): void
    {
        $this->assertFalse(ModuleServiceProvider::enabled('nonexistent_module'));
    }

    public function test_all_returns_array_of_module_states(): void
    {
        config(['modules' => [
            'stripe' => true,
            'oauth' => false,
            'web_push' => true,
        ]]);

        $all = ModuleServiceProvider::all();
        $this->assertIsArray($all);
        $this->assertTrue($all['stripe']);
        $this->assertFalse($all['oauth']);
        $this->assertTrue($all['web_push']);
    }

    public function test_all_casts_values_to_boolean(): void
    {
        config(['modules' => [
            'feature_a' => 1,
            'feature_b' => 0,
            'feature_c' => '',
        ]]);

        $all = ModuleServiceProvider::all();
        $this->assertTrue($all['feature_a']);
        $this->assertFalse($all['feature_b']);
        $this->assertFalse($all['feature_c']);
    }
}
