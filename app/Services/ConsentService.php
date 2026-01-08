<?php

namespace App\Services;

use App\Mail\ReminderMail;
use App\Models\NotificationConsent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ConsentService
{

    /**
     * Check if an opt-in email can be sent to a recipient.
     *
     * @return array{can_send: bool, reason: ?string, consent: NotificationConsent}
     */
    public function canSendOptinEmail(User $sender, string $recipientEmail): array
    {
        $consent = NotificationConsent::findOrCreateForEmail($recipientEmail);

        // Check recipient-level limits first
        if (!$consent->canSendOptinEmail()) {
            return [
                'can_send' => false,
                'reason' => $consent->getOptinBlockReason(),
                'consent' => $consent,
            ];
        }

        // Check sender limits
        $senderLimit = $this->checkSenderLimits($sender);
        if (!$senderLimit['allowed']) {
            return [
                'can_send' => false,
                'reason' => $senderLimit['reason'],
                'consent' => $consent,
            ];
        }

        return [
            'can_send' => true,
            'reason' => null,
            'consent' => $consent,
        ];
    }

    /**
     * Check sender's daily limits.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function checkSenderLimits(User $sender): array
    {
        $limits = $sender->getPlanLimits();

        // Count new recipients today
        $newRecipientsToday = $this->countNewRecipientsToday($sender);
        $maxNewRecipients = $limits['new_recipients_per_day'] ?? 5;
        if ($newRecipientsToday >= $maxNewRecipients) {
            return [
                'allowed' => false,
                'reason' => 'sender_limit_new_recipients',
            ];
        }

        // Count opt-in emails sent today
        $optinEmailsToday = $this->countOptinEmailsToday($sender);
        $maxOptinEmails = $limits['optin_emails_per_day'] ?? 10;
        if ($optinEmailsToday >= $maxOptinEmails) {
            return [
                'allowed' => false,
                'reason' => 'sender_limit_optin_emails',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Count new recipients the user has contacted today.
     */
    protected function countNewRecipientsToday(User $sender): int
    {
        // Count unique recipient emails from reminder actions created today
        // We need to extract emails from the JSON escalation_rules->recipients array
        $actions = DB::table('scheduled_actions')
            ->where('owner_user_id', $sender->id)
            ->where('type', 'reminder')
            ->where('created_at', '>=', now()->startOfDay())
            ->pluck('escalation_rules');

        $uniqueRecipients = collect();
        foreach ($actions as $rules) {
            $decoded = is_string($rules) ? json_decode($rules, true) : $rules;
            $recipients = $decoded['recipients'] ?? [];
            foreach ($recipients as $recipient) {
                $uniqueRecipients->push(strtolower(trim($recipient)));
            }
        }

        return $uniqueRecipients->unique()->count();
    }

    /**
     * Count opt-in emails sent by user today.
     */
    protected function countOptinEmailsToday(User $sender): int
    {
        // Track via scheduled_actions that are in awaiting_consent state
        return DB::table('scheduled_actions')
            ->where('owner_user_id', $sender->id)
            ->where('type', 'reminder')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    /**
     * Record that an opt-in email was sent.
     */
    public function recordOptinSent(NotificationConsent $consent, User $sender): void
    {
        $consent->recordOptinEmailSent();

        Log::info('Opt-in email sent', [
            'recipient' => $consent->email,
            'sender_id' => $sender->id,
        ]);
    }

    /**
     * Process an opt-in acceptance.
     */
    public function acceptOptIn(string $token): ?NotificationConsent
    {
        $consent = NotificationConsent::where('consent_token', $token)->first();

        if (!$consent) {
            return null;
        }

        if ($consent->suppressed) {
            Log::warning('Attempted to opt-in suppressed recipient', [
                'email' => $consent->email,
            ]);
            return null;
        }

        $consent->optIn();

        Log::info('Recipient opted in', [
            'email' => $consent->email,
        ]);

        // Process any pending reminders for this recipient
        $this->processPendingReminders($consent->email);

        return $consent;
    }

    /**
     * Send reminders that were waiting for consent.
     */
    protected function processPendingReminders(string $email): void
    {
        $pendingRecipients = ReminderRecipient::where('email', $email)
            ->where('status', ReminderRecipient::STATUS_AWAITING_CONSENT)
            ->get();

        foreach ($pendingRecipients as $recipient) {
            /** @var ScheduledAction $action */
            $action = $recipient->action;

            // Only send if the action is still awaiting response
            if ($action->resolution_status !== ScheduledAction::STATUS_AWAITING_RESPONSE) {
                $recipient->update(['status' => ReminderRecipient::STATUS_SUPPRESSED]);
                continue;
            }

            // Send the reminder email
            try {
                Mail::to($recipient->email)->send(new ReminderMail($action, $recipient));

                $recipient->update(['status' => ReminderRecipient::STATUS_PENDING]);

                Log::info('Sent pending reminder after consent', [
                    'action_id' => $action->id,
                    'recipient' => $recipient->email,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send pending reminder after consent', [
                    'action_id' => $action->id,
                    'recipient' => $recipient->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process an opt-out/decline.
     */
    public function declineOptIn(string $token, string $reason = 'user_declined'): ?NotificationConsent
    {
        $consent = NotificationConsent::where('consent_token', $token)->first();

        if (!$consent) {
            return null;
        }

        $consent->optOut($reason);

        Log::info('Recipient opted out', [
            'email' => $consent->email,
            'reason' => $reason,
        ]);

        return $consent;
    }

    /**
     * Unsubscribe a recipient (from email link).
     */
    public function unsubscribe(string $email): bool
    {
        $consent = NotificationConsent::where('email', NotificationConsent::normalizeEmail($email))->first();

        if (!$consent) {
            return false;
        }

        $consent->optOut('unsubscribed');

        Log::info('Recipient unsubscribed', [
            'email' => $consent->email,
        ]);

        return true;
    }

    /**
     * Check if a recipient can receive reminders.
     */
    public function canReceiveReminders(string $email): bool
    {
        $consent = NotificationConsent::where('email', NotificationConsent::normalizeEmail($email))->first();

        return $consent?->canReceiveReminders() ?? false;
    }

    /**
     * Get consent status for an email.
     */
    public function getConsentStatus(string $email): ?NotificationConsent
    {
        return NotificationConsent::where('email', NotificationConsent::normalizeEmail($email))->first();
    }

    /**
     * Get all pending consents (for admin).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, NotificationConsent>
     */
    public function getPendingConsents()
    {
        return NotificationConsent::where('status', NotificationConsent::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get suppressed emails (for admin).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, NotificationConsent>
     */
    public function getSuppressedEmails()
    {
        return NotificationConsent::where('suppressed', true)
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
