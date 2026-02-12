<?php

namespace App\Services;

use App\Jobs\DeliverActionCallback;
use App\Models\ScheduledAction;
use App\Models\Webhook;

/**
 * Dispatches callbacks to all configured endpoints.
 *
 * Handles both:
 * - Action-specific callback_url (legacy per-action callbacks)
 * - Registered account webhooks (new account-level webhooks)
 */
class CallbackDispatcher
{
    /**
     * Dispatch callback for an action event to all configured endpoints.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function dispatch(ScheduledAction $action, string $event, array $metadata = []): void
    {
        $urls = [];

        // 1. Action's callback_url (legacy support)
        if ($action->callback_url) {
            $urls[] = [
                'url' => $action->callback_url,
                'secret' => null, // Uses account webhook secret
            ];
        }

        // 2. Registered webhooks for the account
        $webhooks = Webhook::getForEvent($action->account_id, $event);
        foreach ($webhooks as $webhook) {
            // Don't duplicate if action callback_url matches a registered webhook
            if ($action->callback_url && $webhook->url === $action->callback_url) {
                continue;
            }

            $urls[] = [
                'url' => $webhook->url,
                'secret' => $webhook->secret,
                'webhook_id' => $webhook->id,
            ];
        }

        // Dispatch a job for each URL
        foreach ($urls as $config) {
            DeliverActionCallback::dispatch(
                $action,
                $event,
                $metadata,
                1, // attemptNumber
                $config['url'],
                $config['secret'] ?? null,
                $config['webhook_id'] ?? null
            );
        }
    }
}
