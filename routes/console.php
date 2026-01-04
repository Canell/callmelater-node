<?php

use App\Jobs\DispatcherJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the dispatcher job to run every minute
Schedule::job(new DispatcherJob())->everyMinute()->withoutOverlapping();

// Check for expired reminders every 5 minutes
Schedule::command('app:check-expired-reminders')->everyFiveMinutes();

// Recover stuck EXECUTING actions every 5 minutes (worker crash recovery)
Schedule::command('app:recover-stuck-executing-actions')->everyFiveMinutes();
