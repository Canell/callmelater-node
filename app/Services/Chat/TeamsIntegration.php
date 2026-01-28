<?php

namespace App\Services\Chat;

use App\Contracts\ChatIntegration;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TeamsIntegration implements ChatIntegration
{
    public function getChannel(): string
    {
        return 'teams';
    }

    /**
     * Send a decision card to a Teams webhook URL.
     *
     * @return array{message_id: string, channel_id: string}
     */
    public function sendDecisionCard(
        ScheduledAction $action,
        ReminderRecipient $recipient,
        string $responseToken
    ): array {
        $webhookUrl = $recipient->chat_destination;

        if (empty($webhookUrl)) {
            throw new \InvalidArgumentException('No Teams webhook URL configured for recipient');
        }

        $card = $this->buildAdaptiveCard($action, $responseToken);

        $response = Http::timeout(30)->post($webhookUrl, [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => $card,
                ],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Failed to send Teams message', [
                'action_id' => $action->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to send Teams message: '.$response->status());
        }

        // Teams webhook doesn't return a message ID, so we generate one for tracking
        $messageId = Str::uuid()->toString();

        Log::info('Teams message sent', [
            'action_id' => $action->id,
            'recipient_id' => $recipient->id,
            'message_id' => $messageId,
        ]);

        return [
            'message_id' => $messageId,
            'channel_id' => $webhookUrl,
        ];
    }

    /**
     * Build an Adaptive Card for the decision prompt.
     *
     * @return array<string, mixed>
     */
    private function buildAdaptiveCard(ScheduledAction $action, string $responseToken): array
    {
        $timeout = $action->getGateTimeout();
        $message = $action->getGateMessage() ?? $action->description ?? '';
        $callbackUrl = route('chat.webhook', ['provider' => 'teams']);

        return [
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'type' => 'AdaptiveCard',
            'version' => '1.4',
            'body' => [
                [
                    'type' => 'TextBlock',
                    'text' => "Action Required: {$action->name}",
                    'weight' => 'bolder',
                    'size' => 'large',
                    'wrap' => true,
                ],
                [
                    'type' => 'TextBlock',
                    'text' => $message,
                    'wrap' => true,
                ],
                [
                    'type' => 'FactSet',
                    'facts' => [
                        [
                            'title' => 'Timeout',
                            'value' => $this->formatTimeout($timeout),
                        ],
                        [
                            'title' => 'From',
                            'value' => config('app.name', 'CallMeLater'),
                        ],
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'Action.Http',
                    'title' => 'Confirm',
                    'method' => 'POST',
                    'url' => $callbackUrl,
                    'headers' => [
                        [
                            'name' => 'Content-Type',
                            'value' => 'application/json',
                        ],
                    ],
                    'body' => json_encode([
                        'action' => 'confirm',
                        'token' => $responseToken,
                        'source' => 'teams',
                    ]),
                    'style' => 'positive',
                ],
                [
                    'type' => 'Action.Http',
                    'title' => 'Decline',
                    'method' => 'POST',
                    'url' => $callbackUrl,
                    'headers' => [
                        [
                            'name' => 'Content-Type',
                            'value' => 'application/json',
                        ],
                    ],
                    'body' => json_encode([
                        'action' => 'decline',
                        'token' => $responseToken,
                        'source' => 'teams',
                    ]),
                    'style' => 'destructive',
                ],
                [
                    'type' => 'Action.Http',
                    'title' => 'Snooze 1h',
                    'method' => 'POST',
                    'url' => $callbackUrl,
                    'headers' => [
                        [
                            'name' => 'Content-Type',
                            'value' => 'application/json',
                        ],
                    ],
                    'body' => json_encode([
                        'action' => 'snooze',
                        'token' => $responseToken,
                        'source' => 'teams',
                    ]),
                ],
            ],
        ];
    }

    /**
     * Format timeout for display.
     */
    private function formatTimeout(string $timeout): string
    {
        if (preg_match('/^(\d+)([hdw])$/', $timeout, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];

            return match ($unit) {
                'h' => "{$value} hour".($value > 1 ? 's' : ''),
                'd' => "{$value} day".($value > 1 ? 's' : ''),
                default => "{$value} week".($value > 1 ? 's' : ''), // 'w'
            };
        }

        return $timeout;
    }

    /**
     * Verify the webhook signature from Teams.
     *
     * For Action.Http callbacks, Teams sends a simple POST without complex signatures.
     * We rely on the token in the payload for security.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        // For Action.Http callbacks, the security is token-based (our response token)
        // The token is included in the payload and validated by ResponseProcessor
        // Additional verification can be added here if needed (e.g., IP allowlist)

        $payload = $request->all();

        // Basic validation: must have action and token
        if (empty($payload['action']) || empty($payload['token'])) {
            Log::warning('Teams webhook missing required fields', [
                'has_action' => isset($payload['action']),
                'has_token' => isset($payload['token']),
            ]);

            return false;
        }

        // Validate action is one of the expected values
        if (! in_array($payload['action'], ['confirm', 'decline', 'snooze'], true)) {
            Log::warning('Teams webhook invalid action', [
                'action' => $payload['action'],
            ]);

            return false;
        }

        return true;
    }

    /**
     * Parse the webhook payload from Teams.
     *
     * @return array{response: string, token: string, user_id: string}
     */
    public function parseWebhookPayload(Request $request): array
    {
        $payload = $request->all();

        return [
            'response' => $payload['action'],
            'token' => $payload['token'],
            'user_id' => $payload['user_id'] ?? 'teams_user', // Teams Action.Http doesn't include user info
        ];
    }

    /**
     * Update a card after response.
     *
     * Note: With Incoming Webhooks, we cannot update existing messages.
     * This would require a full Bot registration with the Bot Framework.
     * For now, this is a no-op.
     */
    public function updateCardWithResponse(
        string $messageId,
        string $channelId,
        string $response,
        string $respondedBy
    ): void {
        // Incoming Webhooks don't support message updates
        // To update messages, you need:
        // 1. A Bot registered with Bot Framework
        // 2. The conversation ID and activity ID
        //
        // For now, we log and skip. The card remains in Teams but is
        // effectively "consumed" since the token is now used.
        Log::info('Teams card update skipped (webhooks do not support updates)', [
            'message_id' => $messageId,
            'response' => $response,
            'responded_by' => $respondedBy,
        ]);
    }
}
