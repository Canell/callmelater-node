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
        private DomainVerificationService $domainVerificationService,
        private QuotaService $quotaService
    ) {}

    /**
     * Create a new scheduled action.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DomainVerificationRequiredException
     */
    public function create(User $user, array $data): ScheduledAction
    {
        $mode = $data['mode'] ?? ScheduledAction::MODE_IMMEDIATE;

        // Check domain verification for any action with a request
        if (isset($data['request'])) {
            $this->checkDomainVerification($user, $data['request']);
        }

        $action = new ScheduledAction;
        $action->account_id = $user->account_id;
        $action->created_by_user_id = $user->id;
        $action->name = $data['name'] ?? ($mode === ScheduledAction::MODE_IMMEDIATE ? 'HTTP Action' : 'Gated Action');
        $action->description = $data['description'] ?? null;
        $action->mode = $mode;
        $action->timezone = $data['timezone'] ?? 'UTC';
        $action->idempotency_key = $data['idempotency_key'] ?? null;
        $action->callback_url = $data['callback_url'] ?? null;

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

        // Request configuration (for immediate mode, or optional for gated)
        if (isset($data['request'])) {
            $action->request = $data['request'];
            $action->max_attempts = $data['max_attempts'] ?? 5;
            $action->retry_strategy = $data['retry_strategy'] ?? 'exponential';
            // Use provided secret, fall back to user's default secret, or generate random
            $action->webhook_secret = $data['webhook_secret'] ?? $user->webhook_secret ?? Str::random(32);
        }

        // Gate configuration (for gated mode)
        if ($mode === ScheduledAction::MODE_GATED && isset($data['gate'])) {
            $action->gate = $data['gate'];
        }

        $action->save();

        // Record action creation for quota tracking
        if ($user->account) {
            $this->quotaService->recordActionCreated($user->account);
        }

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
     * @param  array<string, mixed>  $intent
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
     * Handle a snooze request for a gated action.
     */
    public function snooze(ScheduledAction $action, string $preset): void
    {
        if (! $action->isGated()) {
            throw new \InvalidArgumentException('Only gated actions can be snoozed.');
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
     *
     * @deprecated Use $action->markAsExecuted() directly
     */
    public function markExecuted(ScheduledAction $action): void
    {
        $action->markAsExecuted();
    }

    /**
     * Mark an action as failed.
     *
     * @deprecated Use $action->markAsFailed() directly
     */
    public function markFailed(ScheduledAction $action, string $reason): void
    {
        $action->markAsFailed($reason);
    }

    /**
     * Mark an action as awaiting response (for gated actions).
     *
     * @deprecated Use $action->markAsAwaitingResponse() directly
     */
    public function markAwaitingResponse(ScheduledAction $action, int $tokenExpiryDays = 7): void
    {
        $action->markAsAwaitingResponse($tokenExpiryDays);
    }

    /**
     * Schedule next retry for a failed HTTP delivery.
     *
     * @deprecated Use $action->scheduleNextRetry() directly
     */
    public function scheduleRetry(ScheduledAction $action): void
    {
        $action->scheduleNextRetry();
    }

    /**
     * Check domain verification for actions with HTTP requests.
     *
     * @param  array<string, mixed>  $request
     *
     * @throws DomainVerificationRequiredException
     */
    private function checkDomainVerification(User $user, array $request): void
    {
        // Skip domain verification for admin/system users
        if ($user->is_admin) {
            return;
        }

        $url = $request['url'] ?? null;

        if (! $url) {
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
