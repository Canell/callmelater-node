<?php

namespace App\Console\Commands;

use App\Models\ReminderEvent;
use App\Models\ScheduledAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiredReminders extends Command
{
    protected $signature = 'app:check-expired-reminders';

    protected $description = 'Check for expired reminders and mark them accordingly';

    public function handle(): int
    {
        $expired = ScheduledAction::query()
            ->where('resolution_status', ScheduledAction::STATUS_AWAITING_RESPONSE)
            ->where('type', ScheduledAction::TYPE_REMINDER)
            ->where('token_expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expired as $action) {
            $action->markAsExpired();

            ReminderEvent::create([
                'reminder_id' => $action->id,
                'event_type' => ReminderEvent::TYPE_EXPIRED,
                'captured_timezone' => $action->timezone,
                'notes' => 'Token expired without response',
            ]);

            $count++;
        }

        if ($count > 0) {
            $this->info("Marked {$count} reminder(s) as expired.");
            Log::info("Expired reminders check completed", ['expired_count' => $count]);
        }

        return Command::SUCCESS;
    }
}
