<?php

namespace App\Console\Commands;

use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Console\Command;

class RefillMonthlyCredits extends Command
{
    protected $signature = 'credits:refill-monthly';

    protected $description = 'Refill all active users\' credit balances to their monthly quota (runs 1st of each month)';

    public function handle(): int
    {
        $users = User::where('is_active', true)
            ->where('credits_monthly_quota', '>', 0)
            ->whereNotIn('role', ['admin', 'superadmin'])
            ->where(function ($q) {
                $q->whereNull('credits_last_refilled')
                    ->orWhere('credits_last_refilled', '<', now()->startOfMonth());
            })
            ->get();

        $count = 0;

        foreach ($users as $user) {
            $oldBalance = $user->credits_balance;

            $user->update([
                'credits_balance' => $user->credits_monthly_quota,
                'credits_last_refilled' => now(),
            ]);

            CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => $user->credits_monthly_quota - $oldBalance,
                'type' => 'monthly_refill',
                'description' => "Monthly refill: {$oldBalance} -> {$user->credits_monthly_quota}",
            ]);

            $count++;
        }

        $this->info("Refilled credits for {$count} user(s).");

        return 0;
    }
}
