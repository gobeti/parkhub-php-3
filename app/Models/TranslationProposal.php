<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TranslationProposal extends Model
{
    use HasUuids;

    protected $fillable = [
        'language', 'key', 'current_value', 'proposed_value', 'context',
        'proposed_by', 'status',
        'reviewer_id', 'review_comment',
    ];

    protected function casts(): array
    {
        return [
            'votes_for' => 'integer',
            'votes_against' => 'integer',
        ];
    }

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function votes()
    {
        return $this->hasMany(TranslationVote::class, 'proposal_id');
    }
}
