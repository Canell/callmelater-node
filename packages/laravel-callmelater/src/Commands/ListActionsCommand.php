<?php

namespace CallMeLater\Laravel\Commands;

use CallMeLater\Laravel\CallMeLater;
use Illuminate\Console\Command;

class ListActionsCommand extends Command
{
    protected $signature = 'callmelater:list
                            {--status= : Filter by status (pending, resolved, executed, etc.)}
                            {--type= : Filter by type (webhook, approval)}
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
                    $action['type'] ?? $action['mode'] ?? '-',
                    $action['resolution_status'] ?? $action['status'] ?? '-',
                    $action['execute_at'] ?? $action['scheduled_for'] ?? '-',
                ];
            }

            $this->table(
                ['ID', 'Name', 'Type', 'Status', 'Execute At'],
                $rows
            );

            $total = $result['meta']['total'] ?? 0;
            $to = $result['meta']['to'] ?? count($actions);

            // Handle case where total/to may be arrays
            if (is_array($total)) {
                $total = $total[0] ?? 0;
            }
            if (is_array($to)) {
                $to = $to[0] ?? 0;
            }

            $this->newLine();
            $this->info("Showing {$to} of {$total} actions.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to list actions: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
