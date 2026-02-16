<?php

namespace CallMeLater\Laravel\Commands;

use CallMeLater\Laravel\CallMeLater;
use Illuminate\Console\Command;

class CancelActionCommand extends Command
{
    protected $signature = 'callmelater:cancel {id : The action ID to cancel}';

    protected $description = 'Cancel a scheduled CallMeLater action';

    public function handle(CallMeLater $callMeLater): int
    {
        $id = $this->argument('id');

        if (! $this->confirm("Are you sure you want to cancel action {$id}?")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $action = $callMeLater->cancel($id);

            $this->info("Action cancelled successfully.");
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $action['id']],
                    ['Name', $action['name'] ?? '(unnamed)'],
                    ['Status', $action['resolution_status']],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to cancel action: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
