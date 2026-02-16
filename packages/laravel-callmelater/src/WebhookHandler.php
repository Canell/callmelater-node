<?php

namespace CallMeLater\Laravel;

use CallMeLater\Laravel\Events\ActionExecuted;
use CallMeLater\Laravel\Events\ActionExpired;
use CallMeLater\Laravel\Events\ActionFailed;
use CallMeLater\Laravel\Events\ReminderResponded;
use Illuminate\Http\Request;

class WebhookHandler
{
    protected CallMeLater $callMeLater;

    protected bool $verifySignature = true;

    protected bool $dispatchEvents = true;

    public function __construct(CallMeLater $callMeLater)
    {
        $this->callMeLater = $callMeLater;
    }

    /**
     * Skip signature verification (not recommended for production).
     */
    public function skipVerification(): self
    {
        $this->verifySignature = false;

        return $this;
    }

    /**
     * Don't dispatch Laravel events.
     */
    public function withoutEvents(): self
    {
        $this->dispatchEvents = false;

        return $this;
    }

    /**
     * Handle an incoming webhook request.
     *
     * @return array The parsed event data
     *
     * @throws \InvalidArgumentException if signature is invalid
     */
    public function handle(Request $request): array
    {
        if ($this->verifySignature) {
            $this->callMeLater->verifySignature($request);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? null;

        if ($this->dispatchEvents && $event) {
            $this->dispatchEvent($event, $payload);
        }

        return [
            'event' => $event,
            'action_id' => $payload['action_id'] ?? null,
            'action_name' => $payload['action_name'] ?? null,
            'payload' => $payload,
        ];
    }

    /**
     * Dispatch a Laravel event based on the webhook event type.
     */
    protected function dispatchEvent(string $event, array $payload): void
    {
        match ($event) {
            'action.executed' => event(ActionExecuted::fromPayload($payload)),
            'action.failed' => event(ActionFailed::fromPayload($payload)),
            'action.expired' => event(ActionExpired::fromPayload($payload)),
            'reminder.responded' => event(ReminderResponded::fromPayload($payload)),
            default => null,
        };
    }
}
