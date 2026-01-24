<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ScheduledAction
 */
class ActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasRequest = ! empty($this->request);
        $isGated = $this->mode === 'gated';

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'mode' => $this->mode,
            'status' => $this->resolution_status,
            'timezone' => $this->timezone,

            // Scheduling
            'execute_at' => $this->execute_at_utc?->toIso8601String(),
            'executed_at' => $this->executed_at_utc?->toIso8601String(),
            'failure_reason' => $this->when($this->resolution_status === 'failed', $this->failure_reason),

            // Gate (gated mode)
            'gate' => $this->when($isGated, $this->gate),
            'gate_passed_at' => $this->when($this->gate_passed_at !== null, fn () => $this->gate_passed_at?->toIso8601String()),

            // Request (immediate mode OR gated with request)
            'request' => $this->when($hasRequest, $this->request),
            'attempt_count' => $this->when($hasRequest, $this->attempt_count),
            'max_attempts' => $this->when($hasRequest, $this->max_attempts),
            'retry_strategy' => $this->when($hasRequest, $this->retry_strategy),
            'next_retry_at' => $this->when($this->next_retry_at !== null, fn () => $this->next_retry_at?->toIso8601String()),

            // Gated-specific
            'snooze_count' => $this->when($isGated, $this->snooze_count),
            'callback_url' => $this->when($isGated, $this->callback_url),
            'token_expires_at' => $this->when($this->token_expires_at !== null, fn () => $this->token_expires_at?->toIso8601String()),

            // Retry info (for failed actions)
            'can_retry' => $this->when(
                $this->resolution_status === 'failed',
                fn () => $this->canBeManuallyRetried()
            ),
            'manual_retry_count' => $this->when(
                $this->resolution_status === 'failed',
                $this->manual_retry_count ?? 0
            ),
            'last_manual_retry_at' => $this->when(
                $this->last_manual_retry_at !== null,
                fn () => $this->last_manual_retry_at?->toIso8601String()
            ),

            // Relationships (when loaded)
            'delivery_attempts' => DeliveryAttemptResource::collection($this->whenLoaded('deliveryAttempts')),
            'reminder_events' => ReminderEventResource::collection($this->whenLoaded('reminderEvents')),
            'recipients' => ReminderRecipientResource::collection($this->whenLoaded('recipients')),
            'execution_cycles' => ExecutionCycleResource::collection($this->whenLoaded('executionCycles')),

            // Metadata
            'idempotency_key' => $this->idempotency_key,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
