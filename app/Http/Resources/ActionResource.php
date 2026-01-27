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
     * Round a timestamp up to the next minute.
     * This reflects when the action will actually execute (dispatcher runs every minute).
     */
    private function roundUpToNextMinute(?\Carbon\Carbon $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        // If already at :00 seconds, don't add a minute
        if ($timestamp->second === 0) {
            return $timestamp->toIso8601String();
        }

        return $timestamp->copy()->addMinute()->startOfMinute()->toIso8601String();
    }

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

            // Scheduling - rounded up to next minute (when dispatcher actually runs)
            'execute_at' => $this->roundUpToNextMinute($this->execute_at_utc),
            'executed_at' => $this->executed_at_utc?->toIso8601String(),
            'failure_reason' => $this->when($this->resolution_status === 'failed', $this->failure_reason),
            'replaced_by_action_id' => $this->when($this->replaced_by_action_id !== null, $this->replaced_by_action_id),

            // Gate (gated mode)
            'gate' => $this->when($isGated, $this->gate),
            'gate_passed_at' => $this->when($this->gate_passed_at !== null, fn () => $this->gate_passed_at?->toIso8601String()),

            // Request (immediate mode OR gated with request)
            'request' => $this->when($hasRequest, $this->request),
            'attempt_count' => $this->when($hasRequest, $this->attempt_count),
            'max_attempts' => $this->when($hasRequest, $this->max_attempts),
            'retry_strategy' => $this->when($hasRequest, $this->retry_strategy),
            'next_retry_at' => $this->when($this->next_retry_at !== null, fn () => $this->roundUpToNextMinute($this->next_retry_at)),

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

            // Template
            'template_id' => $this->when($this->template_id !== null, $this->template_id),

            // Metadata
            'idempotency_key' => $this->idempotency_key,
            'coordination_keys' => $this->coordination_keys,
            'coordination_config' => $this->when(! empty($this->coordination_config), $this->coordination_config),
            'coordination_reschedule_count' => $this->when(
                $this->coordination_reschedule_count > 0,
                $this->coordination_reschedule_count
            ),

            // Coordination relationships
            'replaced_by' => $this->when(
                $this->relationLoaded('replacedBy') && $this->replacedBy,
                fn () => [
                    'id' => $this->replacedBy->id,
                    'name' => $this->replacedBy->name,
                    'status' => $this->replacedBy->resolution_status,
                ]
            ),
            'related_actions' => $this->when(
                $this->resource->relationLoaded('relatedActions'),
                fn () => $this->resource->getRelation('relatedActions')->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'status' => $a->resolution_status,
                    'created_at' => $a->created_at->toIso8601String(),
                ])
            ),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
