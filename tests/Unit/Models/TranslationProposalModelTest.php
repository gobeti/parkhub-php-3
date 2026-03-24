<?php

namespace Tests\Unit\Models;

use App\Models\TranslationProposal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationProposalModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_translation_proposal_has_fillable_attributes(): void
    {
        $model = new TranslationProposal;
        $this->assertContains('language', $model->getFillable());
        $this->assertContains('key', $model->getFillable());
        $this->assertContains('current_value', $model->getFillable());
        $this->assertContains('proposed_value', $model->getFillable());
        $this->assertContains('context', $model->getFillable());
        $this->assertContains('proposed_by', $model->getFillable());
        $this->assertContains('status', $model->getFillable());
        $this->assertContains('reviewer_id', $model->getFillable());
        $this->assertContains('review_comment', $model->getFillable());
    }

    public function test_votes_for_cast_to_integer(): void
    {
        $model = new TranslationProposal;
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['votes_for']);
    }

    public function test_votes_against_cast_to_integer(): void
    {
        $model = new TranslationProposal;
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['votes_against']);
    }

    public function test_belongs_to_proposer(): void
    {
        $model = new TranslationProposal;
        $this->assertInstanceOf(BelongsTo::class, $model->proposer());
    }

    public function test_belongs_to_reviewer(): void
    {
        $model = new TranslationProposal;
        $this->assertInstanceOf(BelongsTo::class, $model->reviewer());
    }

    public function test_has_many_votes(): void
    {
        $model = new TranslationProposal;
        $this->assertInstanceOf(HasMany::class, $model->votes());
    }
}
