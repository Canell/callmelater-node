<?php

namespace CallMeLater\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActionFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $actionId,
        public string $actionName,
        public array $failure,
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
            failure: $payload['failure'] ?? [],
            payload: $payload,
        );
    }
}
