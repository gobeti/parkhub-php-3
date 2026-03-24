<?php

namespace Tests\Unit\Models;

use App\Models\TranslationOverride;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationOverrideModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_translation_override_has_fillable_attributes(): void
    {
        $model = new TranslationOverride;
        $this->assertContains('language', $model->getFillable());
        $this->assertContains('key', $model->getFillable());
        $this->assertContains('value', $model->getFillable());
    }

    public function test_translation_override_uses_uuid(): void
    {
        $model = new TranslationOverride;
        $this->assertTrue(in_array(HasUuids::class, class_uses_recursive($model)));
    }
}
