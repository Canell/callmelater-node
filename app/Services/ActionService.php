<?php

namespace App\Services;

use App\Exceptions\DomainVerificationRequiredException;
use App\Jobs\ResolveIntentJob;
use App\Models\ActionCoordinationKey;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
     * @return array{action: ScheduledAction, meta: array<string, mixed>}
     *
     * @throws DomainVerificationRequiredException
     */
    public function create(User $user, array $data): array
    {
        $mode = $data['mode'] ?? ScheduledAction::MODE_IMMEDIATE;
        $coordinationKeys = $data['coordination_keys'] ?? [];
        $onCreateBehavior = $data['coordination']['on_create'] ?? null;
        $meta = [];

        // Check domain verification for any action with a request
        if (isset($data['request'])) {
            $this->checkDomainVerification($user, $data['request']);
        }

        return DB::transaction(function () use ($user, $data, $mode, $coordinationKeys, $onCreateBehavior, &$meta) {
            // Handle coordination behavior before creating
            if ($onCreateBehavior && ! empty($coordinationKeys)) {
                $result = $this->handleCoordinationOnCreate(
                    $user->account_id,
                    $coordinationKeys,
                    $onCreateBehavior
                );

                if ($result['skip']) {
                    return [
                        'action' => $result['existing_action'],
                        'meta' => [
                            'skipped' => true,
                            'reason' => 'existing_action_found',
                        ],
                    ];
                }

                // Store cancelled IDs to link after new action is created
                $meta['cancelled_for_replace'] = $result['cancelled_actions'] ?? [];
            }

            $action = new ScheduledAction;
            $action->account_id = $user->account_id;
            $action->created_by_user_id = $user->id;
            $action->template_id = $data['template_id'] ?? null;
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

            // Store coordination config (on_create and on_execute options)
            if (isset($data['coordination'])) {
                $action->coordination_config = $data['coordination'];
            }

            // Store recurrence config
            if (isset($data['recurrence'])) {
                $action->recurrence_config = $data['recurrence'];
            }

            $action->save();

            // Link replaced actions to this new action
            if (! empty($meta['cancelled_for_replace'])) {
                $replacedIds = [];
                foreach ($meta['cancelled_for_replace'] as $cancelledAction) {
                    $cancelledAction->replaced_by_action_id = $action->id;
                    $cancelledAction->save();
                    $replacedIds[] = $cancelledAction->id;
                }
                $meta['replaced_action_ids'] = $replacedIds;
                unset($meta['cancelled_for_replace']);
            }

            // Attach coordination keys
            $this->attachCoordinationKeys($action, $coordinationKeys);

            // Record action creation for quota tracking
            if ($user->account) {
                $this->quotaService->recordActionCreated($user->account);
            }

            // Dispatch intent resolution job
            ResolveIntentJob::dispatch($action);

            return [
                'action' => $action,
                'meta' => $meta,
            ];
        });
    }

    /**
     * Handle coordination behavior on action creation.
     *
     * @param  array<string>  $keys
     * @return array{skip: bool, existing_action?: ScheduledAction, cancelled_actions?: array<ScheduledAction>}
     */
    private function handleCoordinationOnCreate(string $accountId, array $keys, string $behavior): array
    {
        // Find existing non-terminal actions with matching keys
        $existingActions = ScheduledAction::query()
            ->where('account_id', $accountId)
            ->whereNotIn('resolution_status', ScheduledAction::TERMINAL_STATUSES)
            // Skip actions currently executing (in-flight protection)
            ->where('resolution_status', '!=', ScheduledAction::STATUS_EXECUTING)
            ->whereHas('coordinationKeyRecords', fn ($q) => $q->whereIn('coordination_key', $keys))
            ->orderBy('created_at', 'desc')
            ->get();

        if ($existingActions->isEmpty()) {
            return ['skip' => false, 'cancelled_actions' => []];
        }

        if ($behavior === 'skip_if_exists') {
            return ['skip' => true, 'existing_action' => $existingActions->first()];
        }

        // replace_existing - cancel all matching actions
        $cancelledActions = [];
        foreach ($existingActions as $action) {
            if ($action->canBeCancelled()) {
                $action->cancel();
                $cancelledActions[] = $action;
            }
        }

        return ['skip' => false, 'cancelled_actions' => $cancelledActions];
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

    /**
     * Attach coordination keys to an action.
     *
     * @param  array<string>  $keys
     */
    private function attachCoordinationKeys(ScheduledAction $action, array $keys): void
    {
        // Deduplicate keys
        $keys = array_unique($keys);

        foreach ($keys as $key) {
            ActionCoordinationKey::create([
                'action_id' => $action->id,
                'coordination_key' => $key,
            ]);
        }
    }
}
