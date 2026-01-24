<?php

namespace App\Services;

use App\Jobs\DeliverHttpAction;
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

        // Check if action should proceed based on confirmation mode
        $shouldProceed = $this->checkShouldProceed($action);

        if ($shouldProceed) {
            if ($action->hasRequest()) {
                // Gated action with request - execute HTTP on approval
                $this->executeGatedRequest($action);
            } else {
                // Callback-only gated action
                $this->actionService->markExecuted($action);
            }
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
        if ($action->getConfirmationMode() === ScheduledAction::CONFIRMATION_FIRST_RESPONSE) {
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
            throw new \InvalidArgumentException('Maximum snoozes reached for this action.');
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

        // Reset all recipient statuses for the snoozed action
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
     * Check if the action should proceed based on confirmation mode.
     */
    private function checkShouldProceed(ScheduledAction $action): bool
    {
        $confirmationMode = $action->getConfirmationMode();

        if ($confirmationMode === ScheduledAction::CONFIRMATION_FIRST_RESPONSE) {
            return true;
        }

        if ($confirmationMode === ScheduledAction::CONFIRMATION_ALL_REQUIRED) {
            return $this->checkAllConfirmed($action);
        }

        return false;
    }

    /**
     * Check if all recipients have confirmed.
     */
    private function checkAllConfirmed(ScheduledAction $action): bool
    {
        $totalRecipients = $action->recipients()->count();
        $confirmedRecipients = $action->recipients()
            ->where('status', ReminderRecipient::STATUS_CONFIRMED)
            ->count();

        return $confirmedRecipients >= $totalRecipients;
    }

    /**
     * Execute HTTP request for a gated action that was approved.
     */
    private function executeGatedRequest(ScheduledAction $action): void
    {
        // Mark gate as passed and prepare for HTTP execution
        $action->gate_passed_at = now();
        $action->resolution_status = ScheduledAction::STATUS_RESOLVED;
        $action->execute_at_utc = now();
        $action->save();

        // Dispatch HTTP execution immediately
        DeliverHttpAction::dispatch($action);
    }

    /**
     * Get a user-friendly success message for a response type.
     */
    public function getSuccessMessage(string $response): string
    {
        return match ($response) {
            'confirm' => 'Thank you! Your confirmation has been recorded.',
            'decline' => 'Your response has been recorded.',
            'snooze' => 'Action snoozed. You will receive another notification soon.',
            default => 'Your response has been recorded.',
        };
    }

    /**
     * Dispatch callback webhook if configured.
     *
     * This is BEST-EFFORT delivery - the callback is dispatched asynchronously
     * and never affects the action outcome.
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
