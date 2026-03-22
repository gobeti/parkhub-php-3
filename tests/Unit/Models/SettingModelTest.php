<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_and_get_value(): void
    {
        Setting::set('test_key', 'test_value');
        $this->assertEquals('test_value', Setting::get('test_key'));
    }

    public function test_get_returns_default_when_not_set(): void
    {
        $this->assertEquals('fallback', Setting::get('nonexistent_key', 'fallback'));
    }

    public function test_set_overwrites_existing_value(): void
    {
        Setting::set('overwrite_key', 'old');
        Setting::set('overwrite_key', 'new');
        $this->assertEquals('new', Setting::get('overwrite_key'));
    }

    public function test_fillable_attributes(): void
    {
        $setting = new Setting;
        $this->assertContains('key', $setting->getFillable());
        $this->assertContains('value', $setting->getFillable());
    }

    public function test_get_returns_null_default(): void
    {
        $this->assertNull(Setting::get('missing_key'));
    }
}
