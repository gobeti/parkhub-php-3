<?php

namespace Tests\Unit\Models;

use App\Models\Favorite;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorite_has_fillable_attributes(): void
    {
        $model = new Favorite;
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('slot_id', $model->getFillable());
    }

    public function test_belongs_to_slot(): void
    {
        $model = new Favorite;
        $this->assertInstanceOf(BelongsTo::class, $model->slot());
    }
}
