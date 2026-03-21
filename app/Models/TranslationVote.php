<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TranslationVote extends Model
{
    use HasUuids;

    protected $fillable = ['proposal_id', 'user_id', 'vote'];

    public function proposal()
    {
        return $this->belongsTo(TranslationProposal::class, 'proposal_id');
    }
}
