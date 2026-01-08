<?php

namespace App\Services;

use App\Exceptions\DomainVerificationRequiredException;
use App\Jobs\ResolveIntentJob;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Support\Str;

class ActionService
{
    public function __construct(
        private DomainVerificationService $domainVerificationService
    ) {}

    /**
     * Create a new scheduled action.
     *
     * @param array<string, mixed> $data
     * @throws DomainVerificationRequiredException
     */
    public function create(User $user, array $data): ScheduledAction
    {
        // Check domain verification for HTTP actions
        if (($data['type'] ?? '') === ScheduledAction::TYPE_HTTP) {
            $this->checkDomainVerification($user, $data);
        }
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
            $action->callback_url = $data['callback_url'] ?? null;
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
        $action->cancel();
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
     * @deprecated Use $action->markAsExecuted() directly
     */
    public function markExecuted(ScheduledAction $action): void
    {
        $action->markAsExecuted();
    }

    /**
     * Mark an action as failed.
     * @deprecated Use $action->markAsFailed() directly
     */
    public function markFailed(ScheduledAction $action, string $reason): void
    {
        $action->markAsFailed($reason);
    }

    /**
     * Mark an action as awaiting response (for reminders).
     * @deprecated Use $action->markAsAwaitingResponse() directly
     */
    public function markAwaitingResponse(ScheduledAction $action, int $tokenExpiryDays = 7): void
    {
        $action->markAsAwaitingResponse($tokenExpiryDays);
    }

    /**
     * Schedule next retry for a failed HTTP delivery.
     * @deprecated Use $action->scheduleNextRetry() directly
     */
    public function scheduleRetry(ScheduledAction $action): void
    {
        $action->scheduleNextRetry();
    }

    /**
     * Check domain verification for HTTP actions.
     *
     * @param array<string, mixed> $data
     * @throws DomainVerificationRequiredException
     */
    private function checkDomainVerification(User $user, array $data): void
    {
        $httpRequest = $data['http_request'] ?? [];
        $url = $httpRequest['url'] ?? null;

        if (!$url) {
            return;
        }

        $check = $this->domainVerificationService->checkVerificationRequired($user, $url);

        if ($check['required']) {
            throw new DomainVerificationRequiredException(
                $check['domain'],
                $check['token'],
                $check['reason']
            );
        }
    }
}
