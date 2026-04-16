<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TranslationOverride;
use App\Models\TranslationProposal;
use App\Models\TranslationVote;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    // GET /api/v1/translations/overrides — get all approved overrides
    public function overrides()
    {
        $overrides = TranslationOverride::all();

        return response()->json($overrides);
    }

    // GET /api/v1/translations/proposals — list proposals (optionally filtered by status)
    public function proposals(Request $request)
    {
        $query = TranslationProposal::with(['proposer:id,name', 'reviewer:id,name']);

        if ($request->has('status') && $request->status !== 'all') {
            if (in_array($request->status, ['pending', 'approved', 'rejected'])) {
                $query->where('status', $request->status);
            }
        }

        $proposals = $query->orderByDesc('created_at')->get()->map(function ($p) use ($request) {
            $userVote = null;
            if ($request->user()) {
                $vote = TranslationVote::where('proposal_id', $p->id)
                    ->where('user_id', $request->user()->id)
                    ->first();
                $userVote = $vote?->vote;
            }

            return [
                'id' => $p->id,
                'language' => $p->language,
                'key' => $p->key,
                'current_value' => $p->current_value,
                'proposed_value' => $p->proposed_value,
                'context' => $p->context,
                'proposed_by' => $p->proposed_by,
                'proposed_by_name' => $p->proposer?->name ?? 'Unknown',
                'status' => $p->status,
                'votes_for' => $p->votes_for,
                'votes_against' => $p->votes_against,
                'user_vote' => $userVote,
                'reviewer_id' => $p->reviewer_id,
                'reviewer_name' => $p->reviewer?->name,
                'review_comment' => $p->review_comment,
                'created_at' => $p->created_at->toISOString(),
                'updated_at' => $p->updated_at->toISOString(),
            ];
        });

        return response()->json($proposals);
    }

    // GET /api/v1/translations/proposals/{id}
    public function showProposal(string $id)
    {
        $p = TranslationProposal::with(['proposer:id,name', 'reviewer:id,name'])->findOrFail($id);

        return response()->json([
            'id' => $p->id,
            'language' => $p->language,
            'key' => $p->key,
            'current_value' => $p->current_value,
            'proposed_value' => $p->proposed_value,
            'context' => $p->context,
            'proposed_by' => $p->proposed_by,
            'proposed_by_name' => $p->proposer?->name ?? 'Unknown',
            'status' => $p->status,
            'votes_for' => $p->votes_for,
            'votes_against' => $p->votes_against,
            'reviewer_id' => $p->reviewer_id,
            'reviewer_name' => $p->reviewer?->name,
            'review_comment' => $p->review_comment,
            'created_at' => $p->created_at->toISOString(),
            'updated_at' => $p->updated_at->toISOString(),
        ]);
    }

    // POST /api/v1/translations/proposals — create a proposal
    public function createProposal(Request $request)
    {
        $validated = $request->validate([
            'language' => 'required|string|max:10',
            'key' => 'required|string|max:255',
            'proposed_value' => 'required|string|max:5000',
            'context' => 'nullable|string|max:1000',
        ]);

        $proposal = TranslationProposal::create([
            'language' => $validated['language'],
            'key' => $validated['key'],
            'current_value' => $validated['key'], // Frontend will provide the actual current value
            'proposed_value' => $validated['proposed_value'],
            'context' => $validated['context'] ?? null,
            'proposed_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        $proposal->load(['proposer:id,name']);

        return response()->json([
            'id' => $proposal->id,
            'language' => $proposal->language,
            'key' => $proposal->key,
            'current_value' => $proposal->current_value,
            'proposed_value' => $proposal->proposed_value,
            'context' => $proposal->context,
            'proposed_by' => $proposal->proposed_by,
            'proposed_by_name' => $proposal->proposer?->name ?? 'Unknown',
            'status' => $proposal->status,
            'votes_for' => 0,
            'votes_against' => 0,
            'user_vote' => null,
            'reviewer_id' => null,
            'reviewer_name' => null,
            'review_comment' => null,
            'created_at' => $proposal->created_at->toISOString(),
            'updated_at' => $proposal->updated_at->toISOString(),
        ], 201);
    }

    // POST /api/v1/translations/proposals/{id}/vote
    public function vote(Request $request, string $id)
    {
        $validated = $request->validate([
            'vote' => 'required|in:up,down',
        ]);

        $proposal = TranslationProposal::findOrFail($id);

        if ($proposal->status !== 'pending') {
            return response()->json(['success' => false, 'data' => null, 'error' => ['code' => 'NOT_PENDING', 'message' => 'Can only vote on pending proposals'], 'meta' => null], 400);
        }

        if ((string) $proposal->proposed_by === (string) $request->user()->id) {
            return response()->json(['success' => false, 'data' => null, 'error' => ['code' => 'OWN_PROPOSAL', 'message' => 'Cannot vote on your own proposal'], 'meta' => null], 400);
        }

        $existingVote = TranslationVote::where('proposal_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existingVote) {
            // Change vote
            $oldVote = $existingVote->vote;
            if ($oldVote === $validated['vote']) {
                // Same vote — remove it (toggle off)
                $existingVote->delete();
                if ($oldVote === 'up') {
                    $proposal->decrement('votes_for');
                } else {
                    $proposal->decrement('votes_against');
                }
            } else {
                // Switch vote
                $existingVote->update(['vote' => $validated['vote']]);
                if ($validated['vote'] === 'up') {
                    $proposal->increment('votes_for');
                    $proposal->decrement('votes_against');
                } else {
                    $proposal->decrement('votes_for');
                    $proposal->increment('votes_against');
                }
            }
        } else {
            // New vote
            TranslationVote::create([
                'proposal_id' => $id,
                'user_id' => $request->user()->id,
                'vote' => $validated['vote'],
            ]);
            if ($validated['vote'] === 'up') {
                $proposal->increment('votes_for');
            } else {
                $proposal->increment('votes_against');
            }
        }

        $proposal->refresh();
        $proposal->load(['proposer:id,name', 'reviewer:id,name']);

        $userVote = TranslationVote::where('proposal_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'id' => $proposal->id,
            'language' => $proposal->language,
            'key' => $proposal->key,
            'current_value' => $proposal->current_value,
            'proposed_value' => $proposal->proposed_value,
            'context' => $proposal->context,
            'proposed_by' => $proposal->proposed_by,
            'proposed_by_name' => $proposal->proposer?->name ?? 'Unknown',
            'status' => $proposal->status,
            'votes_for' => $proposal->votes_for,
            'votes_against' => $proposal->votes_against,
            'user_vote' => $userVote?->vote,
            'reviewer_id' => $proposal->reviewer_id,
            'reviewer_name' => $proposal->reviewer?->name,
            'review_comment' => $proposal->review_comment,
            'created_at' => $proposal->created_at->toISOString(),
            'updated_at' => $proposal->updated_at->toISOString(),
        ]);
    }

    // PUT /api/v1/translations/proposals/{id}/review — admin approve/reject
    public function review(Request $request, string $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:1000',
        ]);

        $proposal = TranslationProposal::findOrFail($id);

        if ($proposal->status !== 'pending') {
            return response()->json(['success' => false, 'data' => null, 'error' => ['code' => 'NOT_PENDING', 'message' => 'Proposal already reviewed'], 'meta' => null], 400);
        }

        $proposal->update([
            'status' => $validated['status'],
            'reviewer_id' => $request->user()->id,
            'review_comment' => $validated['comment'] ?? null,
        ]);

        // If approved, create/update the override
        if ($validated['status'] === 'approved') {
            TranslationOverride::updateOrCreate(
                ['language' => $proposal->language, 'key' => $proposal->key],
                ['value' => $proposal->proposed_value]
            );
        }

        $proposal->refresh();
        $proposal->load(['proposer:id,name', 'reviewer:id,name']);

        return response()->json([
            'id' => $proposal->id,
            'language' => $proposal->language,
            'key' => $proposal->key,
            'current_value' => $proposal->current_value,
            'proposed_value' => $proposal->proposed_value,
            'context' => $proposal->context,
            'proposed_by' => $proposal->proposed_by,
            'proposed_by_name' => $proposal->proposer?->name ?? 'Unknown',
            'status' => $proposal->status,
            'votes_for' => $proposal->votes_for,
            'votes_against' => $proposal->votes_against,
            'user_vote' => null,
            'reviewer_id' => $proposal->reviewer_id,
            'reviewer_name' => $proposal->reviewer?->name,
            'review_comment' => $proposal->review_comment,
            'created_at' => $proposal->created_at->toISOString(),
            'updated_at' => $proposal->updated_at->toISOString(),
        ]);
    }
}
