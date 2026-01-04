<?php

namespace App\Services;

use App\Jobs\ResolveIntentJob;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Support\Str;

class ActionService
{

    /**
     * Create a new scheduled action.
     *
     * @param array<string, mixed> $data
     */
    public function create(User $user, array $data): ScheduledAction
    {
        $action = new ScheduledAction();
        $action->owner_user_id = $user->id;
        $action->owner_team_id = $data['team_id'] ?? null;
        $action->name = $data['name'];
        $action->description = $data['description'] ?? null;
        $action->type = $data['type'];
        $action->timezone = $data['timezone'] ?? 'UTC';
        $action->idempotency_key = $data['idempotency_key'] ?? null;

        // Set intent
        if (isset($data['execute_at'])) {
            // Absolute timestamp provided
            $action->intent_type = ScheduledAction::INTENT_ABSOLUTE;
            $action->intent_payload = ['execute_at' => $data['execute_at']];
        } else {
            // Wall-clock intent (preset, relative delay, etc.)
            $action->intent_type = ScheduledAction::INTENT_WALL_CLOCK;
            $action->intent_payload = $data['intent'] ?? [];
        }

        // Set initial resolution status
        $action->resolution_status = ScheduledAction::STATUS_PENDING_RESOLUTION;

        // Type-specific configuration
        if ($action->isHttp()) {
            $action->http_request = $data['http_request'] ?? [];
            $action->max_attempts = $data['max_attempts'] ?? 5;
            $action->retry_strategy = $data['retry_strategy'] ?? 'exponential';
            $action->webhook_secret = $data['webhook_secret'] ?? Str::random(32);
        } elseif ($action->isReminder()) {
            $action->message = $data['message'] ?? null;
            $action->confirmation_mode = $data['confirmation_mode'] ?? ScheduledAction::CONFIRMATION_FIRST_RESPONSE;
            $action->escalation_rules = $data['escalation_rules'] ?? null;
            $action->max_snoozes = $data['max_snoozes'] ?? 5;
        }

        $action->save();

        // Dispatch intent resolution job
        ResolveIntentJob::dispatch($action);

        return $action;
    }

    /**
     * Cancel a scheduled action.
     */
    public function cancel(ScheduledAction $action): void
    {
        if (in_array($action->resolution_status, [
            ScheduledAction::STATUS_EXECUTED,
            ScheduledAction::STATUS_CANCELLED,
            ScheduledAction::STATUS_EXPIRED,
        ])) {
            throw new \InvalidArgumentException('Cannot cancel an action that is already executed, cancelled, or expired.');
        }

        $action->resolution_status = ScheduledAction::STATUS_CANCELLED;
        $action->save();
    }

    /**
     * Reschedule an action with a new intent.
     *
     * @param array<string, mixed> $intent
     */
    public function reschedule(ScheduledAction $action, array $intent): void
    {
        if ($action->resolution_status === ScheduledAction::STATUS_EXECUTED) {
            throw new \InvalidArgumentException('Cannot reschedule an already executed action.');
        }

        $action->intent_type = ScheduledAction::INTENT_WALL_CLOCK;
        $action->intent_payload = $intent;
        $action->resolution_status = ScheduledAction::STATUS_PENDING_RESOLUTION;
        $action->execute_at_utc = null;
        $action->save();

        // Dispatch intent resolution job
        ResolveIntentJob::dispatch($action);
    }

    /**
     * Handle a snooze request for a reminder.
     */
    public function snooze(ScheduledAction $action, string $preset): void
    {
        if (! $action->isReminder()) {
            throw new \InvalidArgumentException('Only reminders can be snoozed.');
        }

        if (! $action->canSnooze()) {
            throw new \InvalidArgumentException('Maximum snoozes reached.');
        }

        $action->snooze_count++;
        $action->resolution_status = ScheduledAction::STATUS_PENDING_RESOLUTION;
        $action->intent_payload = ['preset' => $preset];
        $action->execute_at_utc = null;
        $action->save();

        // Dispatch intent resolution job
        ResolveIntentJob::dispatch($action);
    }

    /**
     * Mark an action as executed.
     */
    public function markExecuted(ScheduledAction $action): void
    {
        $action->resolution_status = ScheduledAction::STATUS_EXECUTED;
        $action->executed_at_utc = now();
        $action->save();
    }

    /**
     * Mark an action as failed.
     */
    public function markFailed(ScheduledAction $action, string $reason): void
    {
        $action->resolution_status = ScheduledAction::STATUS_FAILED;
        $action->failure_reason = $reason;
        $action->save();
    }

    /**
     * Mark an action as awaiting response (for reminders).
     */
    public function markAwaitingResponse(ScheduledAction $action, int $tokenExpiryDays = 7): void
    {
        $action->resolution_status = ScheduledAction::STATUS_AWAITING_RESPONSE;
        $action->token_expires_at = now()->addDays($tokenExpiryDays);
        $action->save();
    }

    /**
     * Schedule next retry for a failed HTTP delivery.
     */
    public function scheduleRetry(ScheduledAction $action): void
    {
        if (! $action->canRetry()) {
            $this->markFailed($action, 'Max retry attempts reached');
            return;
        }

        $delay = $this->calculateRetryDelay($action->attempt_count, $action->retry_strategy);
        $action->next_retry_at = now()->addSeconds($delay);
        $action->save();
    }

    /**
     * Calculate retry delay based on strategy and attempt count.
     */
    private function calculateRetryDelay(int $attemptCount, ?string $strategy): int
    {
        // Default exponential backoff: 1min, 5min, 15min, 1hr, 4hr
        $delays = [60, 300, 900, 3600, 14400];

        if ($strategy === 'linear') {
            return 300 * $attemptCount; // 5 min increments
        }

        // Exponential (default)
        $index = min($attemptCount, count($delays) - 1);
        return $delays[$index];
    }
}
