<?php

namespace CallMeLater\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActionExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $actionId,
        public string $actionName,
        public array $execution,
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
            execution: $payload['execution'] ?? [],
            payload: $payload,
        );
    }
}
