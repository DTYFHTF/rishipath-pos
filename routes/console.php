<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Notification Automation
Schedule::command('notifications:low-stock')->hourly();
Schedule::command('notifications:payment-reminders')->dailyAt('09:00');

// Loyalty Program Scheduled Tasks
Schedule::command('loyalty:birthday-bonuses')->daily()->at('00:01');

/*
 * Paused: no alert rule or report schedule has ever been created, so these ran
 * roughly 2,900 times a month over empty tables. The features and their admin
 * screens are untouched — create a rule under Alert Rules (or a schedule under
 * Report Schedules) and uncomment the matching line to switch them back on.
 * notifications:send-pending only has work once alerts start firing.
 *
 * Schedule::command('alerts:check')->everyFifteenMinutes();
 * Schedule::command('reports:process-scheduled')->hourly();
 * Schedule::command('notifications:send-pending')->hourly();
 */
