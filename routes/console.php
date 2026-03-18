<?php

use App\Jobs\AutoReleaseBookingsJob;
use App\Jobs\ExpandRecurringBookingsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new AutoReleaseBookingsJob)->everyFiveMinutes();
Schedule::job(new ExpandRecurringBookingsJob)->dailyAt('01:00');
Schedule::command('sanctum:prune-expired', ['--hours' => 168])->daily();
Schedule::command('credits:refill-monthly')->monthlyOn(1, '00:00');
