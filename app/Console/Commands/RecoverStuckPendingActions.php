<?php

namespace App\Console\Commands;

use App\Jobs\ResolveIntentJob;
use App\Models\ScheduledAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecoverStuckPendingActions extends Command
{
    protected $signature = 'app:recover-stuck-pending-actions
                            {--timeout=5 : Minutes before considering an action stuck}
                            {--dry-run : Show what would be recovered without making changes}';

    protected $description = 'Recover actions stuck in pending_resolution state (queue loss recovery)';

    private const DEFAULT_TIMEOUT_MINUTES = 5;

    public function handle(): int
    {
        $timeoutMinutes = (int) $this->option('timeout') ?: self::DEFAULT_TIMEOUT_MINUTES;
        $dryRun = $this->option('dry-run');

        $stuck = ScheduledAction::query()
            ->where('resolution_status', ScheduledAction::STATUS_PENDING_RESOLUTION)
            ->where('created_at', '<', now()->subMinutes($timeoutMinutes))
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck pending_resolution actions found.');
            return Command::SUCCESS;
        }

        $this->warn("Found {$stuck->count()} action(s) stuck in pending_resolution state.");

        $recovered = 0;
        foreach ($stuck as $action) {
            $this->line("  - {$action->id} ({$action->name}) - created {$action->created_at}");

            if ($dryRun) {
                continue;
            }

            // Idempotent: refresh and check state before dispatching
            $action->refresh();
            if ($action->resolution_status !== ScheduledAction::STATUS_PENDING_RESOLUTION) {
                $this->line("    -> Skipped (state changed to {$action->resolution_status})");
                continue;
            }

            ResolveIntentJob::dispatch($action);
            $recovered++;

            Log::info("Manually recovered pending_resolution action", [
                'action_id' => $action->id,
            ]);
        }

        if ($dryRun) {
            $this->info("Dry run complete. Would recover {$stuck->count()} action(s).");
        } else {
            $this->info("Dispatched ResolveIntentJob for {$recovered} action(s).");
            Log::info("Stuck pending_resolution actions recovery completed", ['recovered' => $recovered]);
        }

        return Command::SUCCESS;
    }
}
