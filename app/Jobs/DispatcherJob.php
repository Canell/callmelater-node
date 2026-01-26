<?php

namespace App\Jobs;

use App\Models\ScheduledAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatcherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    private const BATCH_SIZE = 100;

    public function handle(): void
    {
        // First, recover any stuck pending_resolution actions
        $this->recoverPendingResolution();

        $dispatched = 0;

        // Process in batches to avoid memory issues
        do {
            $count = $this->processBatch();
            $dispatched += $count;
        } while ($count === self::BATCH_SIZE);

        if ($dispatched > 0) {
            Log::info('Dispatcher completed', ['dispatched' => $dispatched]);
        }
    }

    private function processBatch(): int
    {
        $actions = $this->fetchDueActions();
        $count = 0;

        foreach ($actions as $action) {
            $this->dispatchAction($action);
            $count++;
        }

        return $count;
    }

    /**
     * Fetch due actions, lock them, and transition to EXECUTING atomically.
     *
     * @return \Illuminate\Support\Collection<int, ScheduledAction>
     */
    private function fetchDueActions()
    {
        return DB::transaction(function () {
            // Use FOR UPDATE SKIP LOCKED for concurrent dispatcher safety
            // This allows multiple workers to process different actions
            // Eager load coordinationKeyRecords for on_execute condition evaluation
            $actions = ScheduledAction::query()
                ->with('coordinationKeyRecords')
                ->where('resolution_status', ScheduledAction::STATUS_RESOLVED)
                ->where(function ($query) {
                    $query->where('execute_at_utc', '<=', now())
                        ->orWhere(function ($q) {
                            $q->whereNotNull('next_retry_at')
                                ->where('next_retry_at', '<=', now());
                        });
                })
                ->orderBy('execute_at_utc')
                ->limit(self::BATCH_SIZE)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            // Transition to EXECUTING inside the same transaction
            // This is the critical fix: actions become non-selectable immediately
            foreach ($actions as $action) {
                // Double-check after lock (cancellation race protection)
                if (! $action->canBeExecuted()) {
                    continue;
                }

                // Evaluate on_execute coordination condition
                if (! $this->evaluateOnExecuteCondition($action)) {
                    $this->handleConditionNotMet($action);

                    continue;
                }

                // Clear retry flag before transitioning (markAsExecuting saves)
                $action->next_retry_at = null;
                $action->markAsExecuting();
            }

            // Return only actions that were successfully transitioned
            return $actions->filter(fn ($a) => $a->isExecuting());
        });
    }

    /**
     * Evaluate the on_execute coordination condition.
     *
     * @return bool True if condition is met (action should execute), false otherwise
     */
    private function evaluateOnExecuteCondition(ScheduledAction $action): bool
    {
        $condition = $action->getOnExecuteCondition();
        if (! $condition) {
            return true; // No condition, proceed
        }

        // Coordination keys are eager loaded in fetchDueActions
        $keys = $action->coordination_keys;
        if (empty($keys)) {
            return true; // No keys, proceed
        }

        // Find other actions with same key(s) that were created before or at the same time
        // We use <= to handle actions created within the same second, and exclude self by id
        $previous = ScheduledAction::query()
            ->where('account_id', $action->account_id)
            ->where('id', '!=', $action->id)
            ->where('created_at', '<=', $action->created_at)
            ->whereHas('coordinationKeyRecords', fn ($q) => $q->whereIn('coordination_key', $keys))
            ->orderBy('created_at', 'desc')
            ->first(['id', 'resolution_status']);

        return match ($condition) {
            'skip_if_previous_pending' => ! $previous || $previous->isTerminal(),
            'execute_if_previous_failed' => $previous && $previous->resolution_status === ScheduledAction::STATUS_FAILED,
            'execute_if_previous_succeeded' => $previous && $previous->resolution_status === ScheduledAction::STATUS_EXECUTED,
            'wait_for_previous' => ! $previous || $previous->isTerminal(),
            default => true,
        };
    }

    /**
     * Handle when on_execute condition is not met.
     */
    private function handleConditionNotMet(ScheduledAction $action): void
    {
        $behavior = $action->getOnConditionNotMet();
        $condition = $action->getOnExecuteCondition();

        match ($behavior) {
            'cancel' => $this->cancelForCondition($action, $condition),
            'reschedule' => $this->rescheduleForCondition($action, $condition),
            'fail' => $this->failForCondition($action, $condition),
            default => $this->cancelForCondition($action, $condition),
        };
    }

    /**
     * Cancel action because coordination condition was not met.
     */
    private function cancelForCondition(ScheduledAction $action, ?string $condition): void
    {
        $action->resolution_status = ScheduledAction::STATUS_CANCELLED;
        $action->failure_reason = "Coordination condition not met: {$condition}";
        $action->save();

        Log::info('Action cancelled due to coordination condition', [
            'action_id' => $action->id,
            'condition' => $condition,
        ]);
    }

    /**
     * Reschedule action because coordination condition was not met.
     */
    private function rescheduleForCondition(ScheduledAction $action, ?string $condition): void
    {
        $maxReschedules = $action->getMaxReschedules();

        if ($action->coordination_reschedule_count >= $maxReschedules) {
            $this->cancelForCondition($action, "{$condition} (max reschedules reached)");

            return;
        }

        $delay = $action->getRescheduleDelay();
        $action->execute_at_utc = now()->addSeconds($delay);
        $action->coordination_reschedule_count++;
        $action->save();

        Log::info('Action rescheduled due to coordination condition', [
            'action_id' => $action->id,
            'condition' => $condition,
            'reschedule_count' => $action->coordination_reschedule_count,
            'next_execute_at' => $action->execute_at_utc,
        ]);
    }

    /**
     * Fail action because coordination condition was not met.
     */
    private function failForCondition(ScheduledAction $action, ?string $condition): void
    {
        $action->resolution_status = ScheduledAction::STATUS_FAILED;
        $action->failure_reason = "Coordination condition not met: {$condition}";
        $action->save();

        Log::info('Action failed due to coordination condition', [
            'action_id' => $action->id,
            'condition' => $condition,
        ]);
    }

    private function dispatchAction(ScheduledAction $action): void
    {
        try {
            if ($action->isImmediate()) {
                // Immediate mode: execute HTTP request
                DeliverHttpAction::dispatch($action);
            } elseif ($action->isGated()) {
                if ($action->gatePassed()) {
                    // Gate already passed (approved): execute the HTTP request
                    DeliverHttpAction::dispatch($action);
                } else {
                    // Gate not passed yet: send gate notification
                    DeliverReminder::dispatch($action);
                }
            }

            Log::debug('Action dispatched', [
                'action_id' => $action->id,
                'mode' => $action->mode,
                'gate_passed' => $action->gatePassed(),
            ]);
        } catch (\Throwable $e) {
            // If dispatch fails, revert to RESOLVED so it can be retried
            $action->resolution_status = ScheduledAction::STATUS_RESOLVED;
            $action->save();

            Log::error('Failed to dispatch action', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Recover actions stuck in pending_resolution state.
     *
     * This handles cases where ResolveIntentJob was lost (e.g., Redis restart).
     * Safe to call multiple times - ResolveIntentJob is idempotent.
     */
    private function recoverPendingResolution(): void
    {
        // Find actions stuck in pending_resolution for more than 5 minutes
        $stuck = ScheduledAction::query()
            ->where('resolution_status', ScheduledAction::STATUS_PENDING_RESOLUTION)
            ->where('created_at', '<', now()->subMinutes(5))
            ->limit(50)
            ->get();

        if ($stuck->isEmpty()) {
            return;
        }

        $recovered = 0;
        foreach ($stuck as $action) {
            // Idempotent: refresh and check state before dispatching
            $action->refresh();
            if ($action->resolution_status !== ScheduledAction::STATUS_PENDING_RESOLUTION) {
                continue;
            }

            ResolveIntentJob::dispatch($action);
            $recovered++;
        }

        if ($recovered > 0) {
            Log::info('Recovered stuck pending_resolution actions', ['count' => $recovered]);
        }
    }
}
