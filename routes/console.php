<?php

use App\Jobs\AutoReleaseBookingsJob;
use App\Jobs\ExpandRecurringBookingsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:auto-release')->everyFiveMinutes();

Schedule::job(new AutoReleaseBookingsJob)->everyFiveMinutes();
Schedule::job(new ExpandRecurringBookingsJob)->dailyAt('01:00');
Schedule::command('sanctum:prune-expired', ['--hours' => 168])->daily();
