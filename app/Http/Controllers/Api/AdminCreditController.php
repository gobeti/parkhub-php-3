<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Http\Request;

class AdminCreditController extends Controller
{
    private function requireAdmin($request): void
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
    }

    public function updateUserQuota(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'monthly_quota' => 'required|integer|min:0|max:999',
        ]);

        $user = User::findOrFail($id);
        $oldQuota = $user->credits_monthly_quota;
        $user->update(['credits_monthly_quota' => $validated['monthly_quota']]);

        CreditTransaction::create([
            'user_id' => $user->id,
            'amount' => $validated['monthly_quota'] - $oldQuota,
            'type' => 'quota_adjustment',
            'description' => "Monthly quota changed: {$oldQuota} -> {$validated['monthly_quota']}",
            'granted_by' => $request->user()->id,
        ]);

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'quota_updated',
            'details' => [
                'target_user' => $user->id,
                'old_quota' => $oldQuota,
                'new_quota' => $validated['monthly_quota'],
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $user->fresh()->toArray(),
        ]);
    }

    public function grantCredits(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'amount' => 'required|integer|min:1|max:1000',
            'description' => 'nullable|string|max:255',
        ]);

        $user = User::findOrFail($id);
        $user->increment('credits_balance', $validated['amount']);

        CreditTransaction::create([
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'type' => 'grant',
            'description' => $validated['description'] ?? 'Admin grant',
            'granted_by' => $request->user()->id,
        ]);

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'credits_granted',
            'details' => ['target_user' => $user->id, 'amount' => $validated['amount']],
        ]);

        return response()->json([
            'success' => true,
            'data' => ['credits_balance' => $user->fresh()->credits_balance],
        ]);
    }

    public function creditTransactions(Request $request)
    {
        $query = CreditTransaction::with('user:id,username,name')
            ->orderBy('created_at', 'desc');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return response()->json([
            'success' => true,
            'data' => $query->limit(200)->get(),
        ]);
    }

    public function refillAllCredits(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'nullable|integer|min:1|max:1000',
        ]);

        $amount = $validated['amount'] ?? null;
        $users = User::where('role', 'user')->where('is_active', true)->get();
        $count = 0;

        foreach ($users as $user) {
            $refillAmount = $amount ?? $user->credits_monthly_quota;
            $user->update([
                'credits_balance' => $refillAmount,
                'credits_last_refilled' => now(),
            ]);
            CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => $refillAmount,
                'type' => 'monthly_refill',
                'description' => 'Monthly credit refill',
                'granted_by' => $request->user()->id,
            ]);
            $count++;
        }

        return response()->json([
            'success' => true,
            'data' => ['users_refilled' => $count],
        ]);
    }
}
