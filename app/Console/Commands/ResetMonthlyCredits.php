<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class ResetMonthlyCredits extends Command
{
    protected $signature = 'credits:reset-monthly';

    protected $description = 'Reset monthly used credits for users with limit > 0';

    public function handle(): void
    {
        $affected = User::where('monthly_credit_limit', '>', 0)->update([
            'monthly_credits_used' => 0,
            'credits_reset_at'     => now(),
        ]);

        $this->info("Reset monthly credits for {$affected} users.");
    }
}
