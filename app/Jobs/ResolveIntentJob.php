<?php

namespace App\Jobs;

use App\Models\ScheduledAction;
use App\Services\IntentResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResolveIntentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public ScheduledAction $action
    ) {}

    public function handle(IntentResolver $resolver): void
    {
        // Only resolve pending actions
        if ($this->action->resolution_status !== ScheduledAction::STATUS_PENDING_RESOLUTION) {
            Log::info("ResolveIntentJob skipped - action not pending", [
                'action_id' => $this->action->id,
                'status' => $this->action->resolution_status,
            ]);
            return;
        }

        try {
            $executeAt = $resolver->resolve(
                $this->action->intent_payload ?? [],
                $this->action->timezone ?? 'UTC'
            );

            $this->action->execute_at_utc = $executeAt;
            $this->action->resolution_status = ScheduledAction::STATUS_RESOLVED;
            $this->action->save();

            Log::info("Intent resolved successfully", [
                'action_id' => $this->action->id,
                'execute_at_utc' => $executeAt->toIso8601String(),
                'timezone' => $this->action->timezone,
            ]);
        } catch (\InvalidArgumentException $e) {
            Log::error("Failed to resolve intent", [
                'action_id' => $this->action->id,
                'error' => $e->getMessage(),
                'intent_payload' => $this->action->intent_payload,
            ]);

            $this->action->resolution_status = ScheduledAction::STATUS_FAILED;
            $this->action->failure_reason = "Intent resolution failed: {$e->getMessage()}";
            $this->action->save();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ResolveIntentJob failed after retries", [
            'action_id' => $this->action->id,
            'error' => $exception->getMessage(),
        ]);

        $this->action->resolution_status = ScheduledAction::STATUS_FAILED;
        $this->action->failure_reason = "Intent resolution failed after retries: {$exception->getMessage()}";
        $this->action->save();
    }
}
