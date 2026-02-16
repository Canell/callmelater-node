<?php

namespace CallMeLater\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReminderResponded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $actionId,
        public string $actionName,
        public string $response,
        public ?string $responderEmail,
        public ?string $respondedAt,
        public ?string $comment,
        public array $payload
    ) {}

    /**
     * Create from webhook payload.
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            actionId: $payload['action_id'],
            actionName: $payload['action_name'] ?? '',
            response: $payload['response'] ?? '',
            responderEmail: $payload['responder_email'] ?? null,
            respondedAt: $payload['responded_at'] ?? null,
            comment: $payload['comment'] ?? null,
            payload: $payload,
        );
    }

    /**
     * Check if the response was a confirmation.
     */
    public function isConfirmed(): bool
    {
        return $this->response === 'confirmed';
    }

    /**
     * Check if the response was a decline.
     */
    public function isDeclined(): bool
    {
        return $this->response === 'declined';
    }

    /**
     * Check if the response was a snooze.
     */
    public function isSnoozed(): bool
    {
        return $this->response === 'snoozed';
    }
}
