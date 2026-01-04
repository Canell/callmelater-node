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
        $dispatched = 0;

        // Process in batches to avoid memory issues
        do {
            $count = $this->processBatch();
            $dispatched += $count;
        } while ($count === self::BATCH_SIZE);

        if ($dispatched > 0) {
            Log::info("Dispatcher completed", ['dispatched' => $dispatched]);
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
            $actions = ScheduledAction::query()
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
                if (!$action->canBeExecuted()) {
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

    private function dispatchAction(ScheduledAction $action): void
    {
        try {
            if ($action->isHttp()) {
                DeliverHttpAction::dispatch($action);
            } elseif ($action->isReminder()) {
                DeliverReminder::dispatch($action);
            }

            Log::debug("Action dispatched", [
                'action_id' => $action->id,
                'type' => $action->type,
            ]);
        } catch (\Throwable $e) {
            // If dispatch fails, revert to RESOLVED so it can be retried
            $action->resolution_status = ScheduledAction::STATUS_RESOLVED;
            $action->save();

            Log::error("Failed to dispatch action", [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
