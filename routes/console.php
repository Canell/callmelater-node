<?php

use App\Jobs\CheckEscalationsJob;
use App\Jobs\DispatcherJob;
use App\Jobs\HealthMonitorJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the dispatcher job to run every minute
Schedule::job(new DispatcherJob)->everyMinute()->withoutOverlapping();

// Check for expired reminders every 5 minutes
Schedule::command('app:check-expired-reminders')->everyFiveMinutes();

// Check for reminders needing escalation every 5 minutes
Schedule::job(new CheckEscalationsJob)->everyFiveMinutes()->withoutOverlapping();

// Recover stuck EXECUTING actions every 5 minutes (worker crash recovery)
Schedule::command('app:recover-stuck-executing-actions')->everyFiveMinutes();

// Health monitor - self-monitoring via dogfooding (every 5 minutes)
Schedule::job(new HealthMonitorJob)->everyFiveMinutes()->withoutOverlapping();
