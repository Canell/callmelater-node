<?php

namespace App\Jobs;

use App\Services\HealthMonitorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HealthMonitorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(HealthMonitorService $healthMonitor): void
    {
        try {
            $results = $healthMonitor->check();

            if (!$results['enabled']) {
                Log::debug("Health monitor: Disabled, skipping check");
                return;
            }

            Log::info("Health monitor: Check completed", [
                'metrics' => $results['metrics'] ?? [],
                'results' => array_map(
                    fn ($r) => $r['action'] ?? 'unknown',
                    $results['results'] ?? []
                ),
            ]);

        } catch (\Throwable $e) {
            Log::error("Health monitor: Check failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
