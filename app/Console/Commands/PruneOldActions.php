<?php

namespace App\Console\Commands;

use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneOldActions extends Command
{
    protected $signature = 'app:prune-old-actions
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--batch-size=100 : Number of actions to process per batch}';

    protected $description = 'Delete completed/failed actions older than the user\'s plan history limit';

    /**
     * Terminal statuses that are eligible for pruning.
     * We don't prune pending/scheduled/awaiting_response actions.
     */
    private const TERMINAL_STATUSES = [
        ScheduledAction::STATUS_EXECUTED,
        ScheduledAction::STATUS_FAILED,
        ScheduledAction::STATUS_CANCELLED,
        ScheduledAction::STATUS_EXPIRED,
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No data will be deleted');
        }

        $this->info('Starting action pruning...');

        $totalDeleted = 0;
        $userStats = [];

        // Process users in chunks to avoid memory issues
        User::query()
            ->select(['id', 'email'])
            ->chunk(100, function ($users) use ($dryRun, $batchSize, &$totalDeleted, &$userStats) {
                foreach ($users as $user) {
                    $deleted = $this->pruneUserActions($user, $dryRun, $batchSize);
                    if ($deleted > 0) {
                        $totalDeleted += $deleted;
                        $userStats[$user->email] = $deleted;
                    }
                }
            });

        if ($totalDeleted > 0) {
            $this->newLine();
            $this->info('Pruning summary:');
            foreach ($userStats as $email => $count) {
                $this->line("  {$email}: {$count} actions");
            }
        }

        $this->newLine();
        $action = $dryRun ? 'Would delete' : 'Deleted';
        $this->info("{$action} {$totalDeleted} actions total.");

        if (! $dryRun && $totalDeleted > 0) {
            Log::info('Pruned old actions', [
                'total_deleted' => $totalDeleted,
                'users_affected' => count($userStats),
            ]);
        }

        return self::SUCCESS;
    }

    private function pruneUserActions(User $user, bool $dryRun, int $batchSize): int
    {
        $historyDays = $user->getPlanLimit('history_days', 365);
        $cutoffDate = now()->subDays($historyDays);

        // Count actions to be pruned
        $query = ScheduledAction::query()
            ->where('owner_user_id', $user->id)
            ->whereIn('resolution_status', self::TERMINAL_STATUSES)
            ->where('created_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            return 0;
        }

        $this->line("User {$user->email}: {$count} actions older than {$historyDays} days");

        if ($dryRun) {
            return $count;
        }

        // Delete in batches to avoid locking issues
        $deleted = 0;
        do {
            $ids = ScheduledAction::query()
                ->where('owner_user_id', $user->id)
                ->whereIn('resolution_status', self::TERMINAL_STATUSES)
                ->where('created_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            // Delete related records first (cascade doesn't work with batch deletes)
            DB::table('delivery_attempts')->whereIn('action_id', $ids)->delete();
            DB::table('reminder_events')->whereIn('action_id', $ids)->delete();
            DB::table('reminder_recipients')->whereIn('action_id', $ids)->delete();

            // Delete the actions
            ScheduledAction::whereIn('id', $ids)->delete();

            $deleted += $ids->count();
            $this->output->write('.');
        } while ($ids->count() === $batchSize);

        $this->newLine();

        return $deleted;
    }
}
