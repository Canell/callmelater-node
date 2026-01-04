<?php

namespace App\Jobs;

use App\Mail\ReminderMail;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DeliverReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ScheduledAction $action
    ) {}

    public function handle(TwilioService $twilioService): void
    {
        // CRITICAL: Verify action is still in EXECUTING state
        // This guards against cancellation race conditions
        $this->action->refresh();
        if (!$this->action->isExecuting()) {
            Log::info("Reminder skipped - no longer in executing state", [
                'action_id' => $this->action->id,
                'status' => $this->action->resolution_status,
            ]);
            return;
        }

        /** @var array<string, mixed>|null $escalationRules */
        $escalationRules = $this->action->escalation_rules;
        if (! is_array($escalationRules)) {
            $escalationRules = [];
        }

        /** @var array<int, string> $recipients */
        $recipients = $escalationRules['recipients'] ?? [];

        if (count($recipients) === 0) {
            Log::error("No recipients configured for reminder", ['action_id' => $this->action->id]);
            $this->action->markAsFailed('No recipients configured');
            return;
        }

        $tokenExpiryDays = $escalationRules['token_expiry_days'] ?? 7;

        // Get notification channels
        /** @var array<int, string> $channels */
        $channels = $escalationRules['channels'] ?? ['email'];

        $sentCount = 0;

        // Create recipient records with response tokens
        foreach ($recipients as $recipient) {
            // Determine if it's an email or phone number
            $isPhone = $this->isPhoneNumber($recipient);
            $isEmail = filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false;

            $recipientRecord = ReminderRecipient::create([
                'action_id' => $this->action->id,
                'email' => $recipient,
                'status' => ReminderRecipient::STATUS_PENDING,
                'response_token' => Str::random(64),
            ]);

            // Send via appropriate channel
            if ($isEmail && in_array('email', $channels)) {
                $this->sendEmail($recipientRecord);
                $sentCount++;
            }

            if ($isPhone && in_array('sms', $channels)) {
                $this->sendSms($recipientRecord, $twilioService);
                $sentCount++;
            }
        }

        // Record the sent event
        ReminderEvent::create([
            'reminder_id' => $this->action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
            'captured_timezone' => $this->action->timezone,
            'notes' => "Sent to {$sentCount} recipient(s) via " . implode(', ', $channels),
        ]);

        // Mark as awaiting response (uses model's state machine)
        $this->action->markAsAwaitingResponse($tokenExpiryDays);

        Log::info("Reminder delivered", [
            'action_id' => $this->action->id,
            'recipients' => count($recipients),
            'sent' => $sentCount,
            'channels' => $channels,
        ]);
    }

    private function sendEmail(ReminderRecipient $recipient): void
    {
        try {
            Mail::to($recipient->email)->send(new ReminderMail($this->action, $recipient));

            Log::info("Reminder email sent", [
                'action_id' => $this->action->id,
                'recipient' => $recipient->email,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send reminder email", [
                'action_id' => $this->action->id,
                'recipient' => $recipient->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendSms(ReminderRecipient $recipient, TwilioService $twilioService): void
    {
        $baseUrl = config('app.url');
        $token = $recipient->response_token;

        $confirmUrl = "{$baseUrl}/respond?token={$token}&response=confirm";
        $declineUrl = "{$baseUrl}/respond?token={$token}&response=decline";

        $twilioService->sendReminderSms(
            $recipient->email, // In this case, it's a phone number stored in the email field
            $this->action->name,
            $confirmUrl,
            $declineUrl
        );
    }

    private function isPhoneNumber(string $value): bool
    {
        // Simple check for phone numbers (starts with + or contains only digits, spaces, dashes)
        return preg_match('/^\+?[\d\s\-\(\)]+$/', $value) === 1 && strlen(preg_replace('/\D/', '', $value)) >= 10;
    }
}
