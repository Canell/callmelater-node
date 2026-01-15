<?php

namespace App\Jobs;

use App\Mail\OptInRequestMail;
use App\Mail\ReminderMail;
use App\Models\NotificationConsent;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Services\ConsentService;
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

    public function handle(TwilioService $twilioService, ConsentService $consentService): void
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
        $awaitingConsentCount = 0;
        $suppressedCount = 0;

        // Get the action owner for sender limits
        $owner = $this->action->owner;

        // Create recipient records with response tokens
        foreach ($recipients as $recipient) {
            // Determine if it's an email or phone number
            $isPhone = $this->isPhoneNumber($recipient);
            $isEmail = filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false;

            // Use firstOrCreate to handle retries/re-dispatches gracefully
            $recipientRecord = ReminderRecipient::firstOrCreate(
                [
                    'action_id' => $this->action->id,
                    'email' => $recipient,
                ],
                [
                    'status' => ReminderRecipient::STATUS_PENDING,
                    'response_token' => Str::random(64),
                ]
            );

            // Skip if already processed (not pending)
            if ($recipientRecord->status !== ReminderRecipient::STATUS_PENDING) {
                continue;
            }

            // For email recipients, check consent status
            if ($isEmail && in_array('email', $channels)) {
                // Skip consent for admin/system-created actions (internal alerts)
                if ($owner?->is_admin) {
                    $this->sendEmail($recipientRecord);
                    $sentCount++;
                    continue;
                }

                $consentStatus = $this->handleEmailConsent($recipient, $consentService, $owner);

                if ($consentStatus === 'opted_in') {
                    $this->sendEmail($recipientRecord);
                    $sentCount++;
                } elseif ($consentStatus === 'pending') {
                    $recipientRecord->update(['status' => ReminderRecipient::STATUS_AWAITING_CONSENT]);
                    $awaitingConsentCount++;
                } else {
                    // Suppressed or opted-out
                    $recipientRecord->update(['status' => ReminderRecipient::STATUS_SUPPRESSED]);
                    $suppressedCount++;
                }
            }

            // For SMS recipients (phone numbers)
            if ($isPhone && in_array('sms', $channels)) {
                // SMS consent is typically stricter - for now, send if allowed
                $this->sendSms($recipientRecord, $twilioService);
                $sentCount++;
            }
        }

        // Build notes for the event
        $notes = "Sent to {$sentCount} recipient(s) via " . implode(', ', $channels);
        if ($awaitingConsentCount > 0) {
            $notes .= ", {$awaitingConsentCount} awaiting consent";
        }
        if ($suppressedCount > 0) {
            $notes .= ", {$suppressedCount} suppressed";
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

        Log::info("Reminder delivered", [
            'action_id' => $this->action->id,
            'recipients' => count($recipients),
            'sent' => $sentCount,
            'awaiting_consent' => $awaitingConsentCount,
            'suppressed' => $suppressedCount,
            'channels' => $channels,
        ]);
    }

    /**
     * Handle email consent checking and opt-in request sending.
     *
     * @return string 'opted_in' | 'pending' | 'suppressed'
     */
    private function handleEmailConsent(string $email, ConsentService $consentService, $owner): string
    {
        // Check if already opted in
        $consent = NotificationConsent::where('email', NotificationConsent::normalizeEmail($email))->first();

        if ($consent) {
            if ($consent->canReceiveReminders()) {
                return 'opted_in';
            }

            if ($consent->suppressed || $consent->status === NotificationConsent::STATUS_OPTED_OUT) {
                return 'suppressed';
            }

            // Pending - check if we can send an opt-in request
            if ($consent->status === NotificationConsent::STATUS_PENDING) {
                $this->trySendOptinRequest($email, $consent, $consentService, $owner);
                return 'pending';
            }
        }

        // No consent record - create one and send opt-in request
        $check = $consentService->canSendOptinEmail($owner, $email);

        if ($check['can_send']) {
            $this->sendOptinRequest($check['consent'], $owner);
            $consentService->recordOptinSent($check['consent'], $owner);
        } else {
            Log::info("Opt-in email suppressed", [
                'email' => $email,
                'reason' => $check['reason'],
            ]);
        }

        return 'pending';
    }

    /**
     * Try to send an opt-in request if rate limits allow.
     */
    private function trySendOptinRequest(string $email, NotificationConsent $consent, ConsentService $consentService, $owner): void
    {
        $check = $consentService->canSendOptinEmail($owner, $email);

        if ($check['can_send']) {
            $this->sendOptinRequest($consent, $owner);
            $consentService->recordOptinSent($consent, $owner);
        }
    }

    /**
     * Send the opt-in request email.
     */
    private function sendOptinRequest(NotificationConsent $consent, $owner): void
    {
        try {
            Mail::to($consent->email)->send(new OptInRequestMail($consent, $owner, $this->action));

            Log::info("Opt-in request sent", [
                'action_id' => $this->action->id,
                'recipient' => $consent->email,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send opt-in request", [
                'action_id' => $this->action->id,
                'recipient' => $consent->email,
                'error' => $e->getMessage(),
            ]);
        }
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
