<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeployCommand extends Command
{
    protected $signature = 'deploy
                            {--fresh : Run fresh migration (dangerous!)}
                            {--seed : Run seeders after migration}
                            {--skip-migrate : Skip database migrations}
                            {--skip-cache : Skip cache optimization}
                            {--skip-queue : Skip queue restart}';

    protected $description = 'Run deployment tasks (migrate, cache, queue restart)';

    public function handle(): int
    {
        $this->info('');
        $this->info('🚀 Starting deployment...');
        $this->info('');

        // 1. Maintenance mode (optional - commented out by default)
        // $this->call('down', ['--retry' => 60]);

        // 2. Run migrations
        if (! $this->option('skip-migrate')) {
            $this->runMigrations();
        }

        // 3. Clear and rebuild caches
        if (! $this->option('skip-cache')) {
            $this->rebuildCaches();
        }

        // 4. Fix file permissions
        $this->fixPermissions();

        // 5. Restart queue workers
        if (! $this->option('skip-queue')) {
            $this->restartQueue();
        }

        // 6. Clear expired tokens and sessions
        $this->cleanup();

        // 7. Back online (if maintenance mode was enabled)
        // $this->call('up');

        $this->info('');
        $this->info('✅ Deployment complete!');
        $this->info('');

        return Command::SUCCESS;
    }

    private function runMigrations(): void
    {
        $this->info('📦 Running migrations...');

        if ($this->option('fresh')) {
            if (app()->environment('production')) {
                if (! $this->confirm('⚠️  You are about to run fresh migrations in PRODUCTION. This will DELETE ALL DATA. Are you sure?', false)) {
                    $this->warn('Migration skipped.');

                    return;
                }
            }
            $this->call('migrate:fresh', [
                '--force' => true,
                '--seed' => $this->option('seed'),
            ]);
        } else {
            $this->call('migrate', [
                '--force' => true,
            ]);

            if ($this->option('seed')) {
                $this->call('db:seed', ['--force' => true]);
            }
        }
    }

    private function rebuildCaches(): void
    {
        $this->info('🗑️  Clearing old caches...');

        // Clear all caches first
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');
        $this->call('event:clear');
        $this->call('cache:clear');

        $this->info('🔧 Rebuilding caches...');

        // Rebuild optimized caches
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');
        $this->call('event:cache');
    }

    private function fixPermissions(): void
    {
        $this->info('🔐 Fixing file permissions...');

        $basePath = base_path();
        $directories = [
            'storage',
            'bootstrap/cache',
        ];

        foreach ($directories as $dir) {
            $path = $basePath.'/'.$dir;
            if (is_dir($path)) {
                exec("chown -R www-data:www-data {$path} 2>&1", $output, $exitCode);
                if ($exitCode !== 0) {
                    $this->warn("  Could not chown {$dir} (may need sudo)");
                }
            }
        }
    }

    private function restartQueue(): void
    {
        $this->info('🔄 Restarting queue workers...');
        $this->call('queue:restart');
    }

    private function cleanup(): void
    {
        $this->info('🧹 Cleaning up...');

        // Clear expired password reset tokens
        $this->callSilently('auth:clear-resets');

        // Prune stale batches (if using job batching)
        $this->callSilently('queue:prune-batches', ['--hours' => 48]);

        // Prune failed jobs older than 7 days
        $this->callSilently('queue:prune-failed', ['--hours' => 168]);
    }
}
