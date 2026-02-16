<?php

namespace CallMeLater\Laravel\Commands;

use CallMeLater\Laravel\CallMeLater;
use Illuminate\Console\Command;

class ListActionsCommand extends Command
{
    protected $signature = 'callmelater:list
                            {--status= : Filter by status (pending, resolved, executed, etc.)}
                            {--type= : Filter by type (http, gate)}
                            {--limit=20 : Number of results to show}';

    protected $description = 'List your scheduled CallMeLater actions';

    public function handle(CallMeLater $callMeLater): int
    {
        $filters = array_filter([
            'status' => $this->option('status'),
            'type' => $this->option('type'),
            'per_page' => $this->option('limit'),
        ]);

        try {
            $result = $callMeLater->list($filters);
            $actions = $result['data'] ?? [];

            if (empty($actions)) {
                $this->info('No actions found.');

                return self::SUCCESS;
            }

            $rows = [];
            foreach ($actions as $action) {
                $rows[] = [
                    $action['id'],
                    substr($action['name'] ?? '(unnamed)', 0, 30),
                    $action['type'],
                    $action['resolution_status'],
                    $action['execute_at_utc'] ?? '-',
                ];
            }

            $this->table(
                ['ID', 'Name', 'Type', 'Status', 'Execute At'],
                $rows
            );

            $this->newLine();
            $this->info("Showing {$result['meta']['to']} of {$result['meta']['total']} actions.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to list actions: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
