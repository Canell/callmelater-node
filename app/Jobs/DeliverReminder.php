<?php

namespace App\Jobs;

use App\Mail\ReminderMail;
use App\Models\BlockedRecipient;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Services\BrevoService;
use App\Services\QuotaService;
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

    public function handle(BrevoService $brevoService, QuotaService $quotaService): void
    {
        // CRITICAL: Verify action is still in EXECUTING state
        // This guards against cancellation race conditions
        $this->action->refresh();
        if (! $this->action->isExecuting()) {
            Log::info('Reminder skipped - no longer in executing state', [
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
            Log::error('No recipients configured for reminder', ['action_id' => $this->action->id]);
            $this->action->markAsFailed('No recipients configured');

            return;
        }

        $tokenExpiryDays = $escalationRules['token_expiry_days'] ?? 7;

        $sentCount = 0;
        $blockedCount = 0;
        $channelsUsed = [];

        // Create recipient records with response tokens and send notifications
        foreach ($recipients as $recipient) {
            // Determine if it's an email or phone number
            $isPhone = $this->isPhoneNumber($recipient);
            $isEmail = filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false;

            // Check if recipient is blocked
            if (BlockedRecipient::isBlocked($recipient)) {
                Log::info('Recipient blocked, skipping', [
                    'action_id' => $this->action->id,
                    'recipient' => $recipient,
                ]);
                $blockedCount++;

                continue;
            }

            // Use firstOrCreate to handle retries/re-dispatches gracefully
            $recipientRecord = ReminderRecipient::firstOrCreate(
                [
                    'action_id' => $this->action->id,
                    'email' => $recipient,
                ],
                [
                    'status' => ReminderRecipient::STATUS_PENDING,
                    'response_token' => Str::random(20),
                ]
            );

            // Skip if already processed (not pending)
            if ($recipientRecord->status !== ReminderRecipient::STATUS_PENDING) {
                continue;
            }

            // Auto-detect channel from recipient type and send
            if ($isEmail) {
                $this->sendEmail($recipientRecord);
                $recipientRecord->update(['status' => ReminderRecipient::STATUS_SENT]);
                $sentCount++;
                $channelsUsed['email'] = true;
            } elseif ($isPhone) {
                // Check SMS quota before sending
                $account = $this->action->account;
                if ($account && ! $quotaService->canSendSms($account)) {
                    Log::warning('SMS quota exceeded, skipping SMS delivery', [
                        'action_id' => $this->action->id,
                        'recipient' => $recipient,
                    ]);

                    continue;
                }

                $this->sendSms($recipientRecord, $brevoService);
                $recipientRecord->update(['status' => ReminderRecipient::STATUS_SENT]);
                $sentCount++;
                $channelsUsed['sms'] = true;

                // Record SMS usage
                if ($account) {
                    $quotaService->recordSmsSent($account);
                }
            }
        }

        // Build notes for the event
        $channels = array_keys($channelsUsed);
        $notes = "Sent to {$sentCount} recipient(s) via ".implode(', ', $channels);
        if ($blockedCount > 0) {
            $notes .= ", {$blockedCount} blocked";
        }

        // Record the sent event
        ReminderEvent::create([
            'reminder_id' => $this->action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
            'captured_timezone' => $this->action->timezone,
            'notes' => $notes,
        ]);

        // Mark as awaiting response (uses model's state machine)
        $this->action->markAsAwaitingResponse($tokenExpiryDays);

        Log::info('Reminder delivered', [
            'action_id' => $this->action->id,
            'recipients' => count($recipients),
            'sent' => $sentCount,
            'blocked' => $blockedCount,
            'channels' => $channels,
        ]);
    }

    private function sendEmail(ReminderRecipient $recipient): void
    {
        try {
            Mail::to($recipient->email)->send(new ReminderMail($this->action, $recipient));

            Log::info('Reminder email sent', [
                'action_id' => $this->action->id,
                'recipient' => $recipient->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send reminder email', [
                'action_id' => $this->action->id,
                'recipient' => $recipient->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendSms(ReminderRecipient $recipient, BrevoService $brevoService): void
    {
        $baseUrl = config('app.url');
        $token = $recipient->response_token;

        // Short URL format for SMS: /r/{token}
        $responseUrl = "{$baseUrl}/r/{$token}";

        $brevoService->sendReminderSms(
            $recipient->email, // In this case, it's a phone number stored in the email field
            $this->action->name,
            $this->action->message,
            $responseUrl
        );
    }

    private function isPhoneNumber(string $value): bool
    {
        // Simple check for phone numbers (starts with + or contains only digits, spaces, dashes)
        return preg_match('/^\+?[\d\s\-\(\)]+$/', $value) === 1 && strlen(preg_replace('/\D/', '', $value)) >= 10;
    }
}
