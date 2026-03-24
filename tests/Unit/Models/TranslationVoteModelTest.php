<?php

namespace Tests\Unit\Models;

use App\Models\TranslationVote;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationVoteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_translation_vote_has_fillable_attributes(): void
    {
        $model = new TranslationVote;
        $this->assertContains('proposal_id', $model->getFillable());
        $this->assertContains('user_id', $model->getFillable());
        $this->assertContains('vote', $model->getFillable());
    }

    public function test_belongs_to_proposal(): void
    {
        $model = new TranslationVote;
        $this->assertInstanceOf(BelongsTo::class, $model->proposal());
    }
}
