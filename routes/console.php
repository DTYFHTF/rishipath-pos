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
 * These were paused while alert_rules and report_schedules were empty — they
 * ran roughly 2,900 times a month over nothing. AutomationSeeder now creates
 * the founders report and the three alert rules, so they have work to do.
 *
 * alerts:check runs hourly rather than every 15 minutes: the rules are daily
 * or hourly, so a quarter-hourly sweep only re-asked a question already
 * answered.
 */
Schedule::command('alerts:check')->hourly();
Schedule::command('reports:process-scheduled')->hourly();
Schedule::command('notifications:send-pending')->hourly();
