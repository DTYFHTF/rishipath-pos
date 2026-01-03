<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Loyalty Program Scheduled Tasks
Schedule::command('loyalty:birthday-bonuses')->daily()->at('00:01');

// Report Scheduling & Automation Tasks
Schedule::command('reports:process-scheduled')->hourly();
Schedule::command('alerts:check')->everyFifteenMinutes();
Schedule::command('notifications:send-pending')->hourly();

