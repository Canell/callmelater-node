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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->resolution_status,
            'timezone' => $this->timezone,

            // Scheduling
            'execute_at' => $this->execute_at_utc?->toIso8601String(),
            'executed_at' => $this->executed_at_utc?->toIso8601String(),
            'failure_reason' => $this->when($this->resolution_status === 'failed', $this->failure_reason),

            // HTTP-specific
            'http_request' => $this->when($this->type === 'http', $this->http_request),
            'attempt_count' => $this->when($this->type === 'http', $this->attempt_count),
            'max_attempts' => $this->when($this->type === 'http', $this->max_attempts),
            'retry_strategy' => $this->when($this->type === 'http', $this->retry_strategy),
            'next_retry_at' => $this->when($this->next_retry_at !== null, fn () => $this->next_retry_at?->toIso8601String()),

            // Reminder-specific
            'message' => $this->when($this->type === 'reminder', $this->message),
            'confirmation_mode' => $this->when($this->type === 'reminder', $this->confirmation_mode),
            'snooze_count' => $this->when($this->type === 'reminder', $this->snooze_count),
            'max_snoozes' => $this->when($this->type === 'reminder', $this->max_snoozes),
            'escalation_rules' => $this->when($this->type === 'reminder', $this->escalation_rules),
            'callback_url' => $this->when($this->type === 'reminder', $this->callback_url),
            'token_expires_at' => $this->when($this->token_expires_at !== null, fn () => $this->token_expires_at?->toIso8601String()),

            // Relationships (when loaded)
            'delivery_attempts' => DeliveryAttemptResource::collection($this->whenLoaded('deliveryAttempts')),
            'reminder_events' => ReminderEventResource::collection($this->whenLoaded('reminderEvents')),
            'recipients' => ReminderRecipientResource::collection($this->whenLoaded('recipients')),

            // Metadata
            'idempotency_key' => $this->idempotency_key,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
