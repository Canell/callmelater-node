<?php

namespace App\Console\Commands;

use App\Models\ScheduledAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecoverStuckExecutingActions extends Command
{
    protected $signature = 'app:recover-stuck-executing-actions
                            {--timeout=10 : Minutes before considering an action stuck}
                            {--dry-run : Show what would be recovered without making changes}';

    protected $description = 'Recover actions stuck in EXECUTING state (worker crash recovery)';

    private const DEFAULT_TIMEOUT_MINUTES = 10;

    public function handle(): int
    {
        $timeoutMinutes = (int) $this->option('timeout') ?: self::DEFAULT_TIMEOUT_MINUTES;
        $dryRun = $this->option('dry-run');

        $stuck = ScheduledAction::query()
            ->where('resolution_status', ScheduledAction::STATUS_EXECUTING)
            ->where('updated_at', '<', now()->subMinutes($timeoutMinutes))
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck actions found.');

            return Command::SUCCESS;
        }

        $this->warn("Found {$stuck->count()} stuck action(s) in EXECUTING state.");

        $recovered = 0;
        foreach ($stuck as $action) {
            $this->line("  - {$action->id} ({$action->name}) - stuck since {$action->updated_at}");

            if ($dryRun) {
                continue;
            }

            // Decide recovery strategy based on retry capability
            if ($action->shouldRetry()) {
                // Return to RESOLVED for re-dispatch
                $action->resolution_status = ScheduledAction::STATUS_RESOLVED;
                $action->next_retry_at = now()->addMinutes(1); // Retry in 1 minute
                $action->save();

                Log::warning('Recovered stuck EXECUTING action - scheduled for retry', [
                    'action_id' => $action->id,
                    'attempt_count' => $action->attempt_count,
                ]);
            } else {
                // No retries left - mark as failed
                $action->markAsFailed('Executor timeout: worker crashed or timed out');

                Log::error('Recovered stuck EXECUTING action - marked as failed (no retries left)', [
                    'action_id' => $action->id,
                    'attempt_count' => $action->attempt_count,
                ]);
            }

            $recovered++;
        }

        if ($dryRun) {
            $this->info("Dry run complete. Would recover {$stuck->count()} action(s).");
        } else {
            $this->info("Recovered {$recovered} action(s).");
            Log::info('Stuck EXECUTING actions recovery completed', ['recovered' => $recovered]);
        }

        return Command::SUCCESS;
    }
}
