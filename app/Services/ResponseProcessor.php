<?php

namespace App\Services;

use App\Jobs\DeliverReminderCallback;
use App\Mail\ReminderDeclinedMail;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use Illuminate\Support\Facades\Mail;

class ResponseProcessor
{
    public function __construct(
        private ActionService $actionService
    ) {}

    /**
     * Process a confirm response from a recipient.
     */
    public function handleConfirm(ReminderRecipient $recipient, ScheduledAction $action): void
    {
        $recipient->status = ReminderRecipient::STATUS_CONFIRMED;
        $recipient->responded_at = now();
        $recipient->save();

        ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_CONFIRMED,
            'actor_email' => $recipient->email,
            'captured_timezone' => $action->timezone,
        ]);

        // Check if action should be marked executed based on confirmation mode
        if ($action->confirmation_mode === ScheduledAction::CONFIRMATION_FIRST_RESPONSE) {
            $this->actionService->markExecuted($action);
        } elseif ($action->confirmation_mode === ScheduledAction::CONFIRMATION_ALL_REQUIRED) {
            $this->checkAllConfirmed($action);
        }

        // Dispatch callback webhook if configured (best-effort, non-blocking)
        $this->dispatchCallback($action, 'confirm', $recipient->email);
    }

    /**
     * Process a decline response from a recipient.
     */
    public function handleDecline(ReminderRecipient $recipient, ScheduledAction $action): void
    {
        $recipient->status = ReminderRecipient::STATUS_DECLINED;
        $recipient->responded_at = now();
        $recipient->save();

        ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_DECLINED,
            'actor_email' => $recipient->email,
            'captured_timezone' => $action->timezone,
        ]);

        // If first_response mode and someone declines, mark as executed (declined)
        if ($action->confirmation_mode === ScheduledAction::CONFIRMATION_FIRST_RESPONSE) {
            $action->resolution_status = ScheduledAction::STATUS_EXECUTED;
            $action->executed_at_utc = now();
            $action->save();
        }

        // Send decline notification to action owner
        $owner = $action->owner;
        if ($owner && $owner->email) {
            Mail::to($owner->email)->queue(new ReminderDeclinedMail($action, $recipient));
        }

        // Dispatch callback webhook if configured (best-effort, non-blocking)
        $this->dispatchCallback($action, 'decline', $recipient->email);
    }

    /**
     * Process a snooze response from a recipient.
     *
     * @throws \InvalidArgumentException if max snoozes reached
     */
    public function handleSnooze(ReminderRecipient $recipient, ScheduledAction $action, string $preset): void
    {
        if (! $action->canSnooze()) {
            throw new \InvalidArgumentException('Maximum snoozes reached for this reminder.');
        }

        $recipient->status = ReminderRecipient::STATUS_SNOOZED;
        $recipient->responded_at = now();
        $recipient->save();

        ReminderEvent::create([
            'reminder_id' => $action->id,
            'event_type' => ReminderEvent::TYPE_SNOOZED,
            'actor_email' => $recipient->email,
            'captured_timezone' => $action->timezone,
            'notes' => "Snoozed with preset: {$preset}",
        ]);

        // Reset all recipient statuses for the snoozed reminder
        ReminderRecipient::query()
            ->where('action_id', $action->id)
            ->update(['status' => ReminderRecipient::STATUS_PENDING, 'responded_at' => null]);

        // Snooze the action (this will re-resolve the intent)
        $this->actionService->snooze($action, $preset);

        // Dispatch callback webhook if configured (best-effort, non-blocking)
        $action->refresh(); // Get updated execute_at_utc after snooze
        $this->dispatchCallback($action, 'snooze', $recipient->email, $preset, $action->execute_at_utc?->toIso8601String());
    }

    /**
     * Process a response by type.
     *
     * @throws \InvalidArgumentException if response type is invalid
     */
    public function process(ReminderRecipient $recipient, ScheduledAction $action, string $response, ?string $preset = null): void
    {
        match ($response) {
            'confirm' => $this->handleConfirm($recipient, $action),
            'decline' => $this->handleDecline($recipient, $action),
            'snooze' => $this->handleSnooze($recipient, $action, $preset ?? '1h'),
            default => throw new \InvalidArgumentException('Invalid response type.'),
        };
    }

    /**
     * Check if all recipients have confirmed and mark action as executed if so.
     */
    private function checkAllConfirmed(ScheduledAction $action): void
    {
        $totalRecipients = $action->recipients()->count();
        $confirmedRecipients = $action->recipients()
            ->where('status', ReminderRecipient::STATUS_CONFIRMED)
            ->count();

        if ($confirmedRecipients >= $totalRecipients) {
            $this->actionService->markExecuted($action);
        }
    }

    /**
     * Get a user-friendly success message for a response type.
     */
    public function getSuccessMessage(string $response): string
    {
        return match ($response) {
            'confirm' => 'Thank you! Your confirmation has been recorded.',
            'decline' => 'Your response has been recorded.',
            'snooze' => 'Reminder snoozed. You will receive another reminder soon.',
            default => 'Your response has been recorded.',
        };
    }

    /**
     * Dispatch callback webhook if configured.
     *
     * This is BEST-EFFORT delivery - the callback is dispatched asynchronously
     * and never affects the reminder outcome.
     */
    private function dispatchCallback(
        ScheduledAction $action,
        string $response,
        string $responderEmail,
        ?string $snoozePreset = null,
        ?string $nextReminderAt = null
    ): void {
        if (! $action->callback_url) {
            return;
        }

        DeliverReminderCallback::dispatch(
            $action,
            $response,
            $responderEmail,
            1, // First attempt
            $snoozePreset,
            $nextReminderAt
        );
    }
}
