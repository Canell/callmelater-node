<?php

namespace App\Services\Chat;

use App\Contracts\ChatIntegration;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackIntegration implements ChatIntegration
{
    public function getChannel(): string
    {
        return 'slack';
    }

    /**
     * Send a decision card to Slack using Block Kit.
     *
     * @return array{message_id: string, channel_id: string}
     */
    public function sendDecisionCard(
        ScheduledAction $action,
        ReminderRecipient $recipient,
        string $responseToken
    ): array {
        $botToken = $recipient->chat_destination;
        $channelId = $recipient->slack_channel_id;

        if (empty($botToken) || empty($channelId)) {
            throw new \InvalidArgumentException('Slack bot token and channel ID are required');
        }

        $blocks = $this->buildBlockKit($action, $responseToken);

        $response = Http::withToken($botToken)
            ->timeout(30)
            ->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channelId,
                'text' => "Action Required: {$action->name}", // Fallback text
                'blocks' => $blocks,
            ]);

        $data = $response->json();

        if (! $response->successful() || ! ($data['ok'] ?? false)) {
            $error = $data['error'] ?? 'Unknown error';
            Log::error('Failed to send Slack message', [
                'action_id' => $action->id,
                'error' => $error,
                'response' => $data,
            ]);
            throw new \RuntimeException("Failed to send Slack message: {$error}");
        }

        $messageId = $data['ts'] ?? '';
        $actualChannelId = $data['channel'] ?? $channelId;

        Log::info('Slack message sent', [
            'action_id' => $action->id,
            'recipient_id' => $recipient->id,
            'message_id' => $messageId,
            'channel_id' => $actualChannelId,
        ]);

        return [
            'message_id' => $messageId,
            'channel_id' => $actualChannelId,
        ];
    }

    /**
     * Build Slack Block Kit blocks for the decision prompt.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildBlockKit(ScheduledAction $action, string $responseToken): array
    {
        $timeout = $action->getGateTimeout();
        $message = $action->getGateMessage() ?? $action->description ?? '';

        return [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => "Action Required: {$action->name}",
                    'emoji' => true,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $message,
                ],
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Timeout:* {$this->formatTimeout($timeout)} | *From:* ".config('app.name', 'CallMeLater'),
                    ],
                ],
            ],
            [
                'type' => 'divider',
            ],
            [
                'type' => 'actions',
                'block_id' => "response_{$responseToken}",
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Confirm',
                            'emoji' => true,
                        ],
                        'style' => 'primary',
                        'action_id' => 'confirm',
                        'value' => json_encode([
                            'action' => 'confirm',
                            'token' => $responseToken,
                        ]),
                    ],
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Decline',
                            'emoji' => true,
                        ],
                        'style' => 'danger',
                        'action_id' => 'decline',
                        'value' => json_encode([
                            'action' => 'decline',
                            'token' => $responseToken,
                        ]),
                    ],
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Snooze 1h',
                            'emoji' => true,
                        ],
                        'action_id' => 'snooze',
                        'value' => json_encode([
                            'action' => 'snooze',
                            'token' => $responseToken,
                        ]),
                    ],
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
     * Verify the webhook signature from Slack.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $signingSecret = config('services.slack.signing_secret');

        if (empty($signingSecret)) {
            Log::warning('Slack signing secret not configured');

            return false;
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (empty($timestamp) || empty($signature)) {
            Log::warning('Slack webhook missing signature headers');

            return false;
        }

        // Check timestamp to prevent replay attacks (5 minute window)
        if (abs(time() - (int) $timestamp) > 300) {
            Log::warning('Slack webhook timestamp too old', [
                'timestamp' => $timestamp,
                'current' => time(),
            ]);

            return false;
        }

        // Compute the signature
        $sigBasestring = "v0:{$timestamp}:{$request->getContent()}";
        $computedSignature = 'v0='.hash_hmac('sha256', $sigBasestring, $signingSecret);

        if (! hash_equals($computedSignature, $signature)) {
            Log::warning('Slack webhook signature mismatch');

            return false;
        }

        return true;
    }

    /**
     * Parse the action response from Slack's interactive webhook payload.
     *
     * @return array{response: string, token: string, user_id: string}
     */
    public function parseWebhookPayload(Request $request): array
    {
        // Slack sends the payload as a form-encoded 'payload' field
        $payloadJson = $request->input('payload');
        $payload = json_decode($payloadJson, true);

        if (! $payload) {
            throw new \InvalidArgumentException('Invalid Slack payload');
        }

        // Extract the button action
        $actions = $payload['actions'] ?? [];
        if (empty($actions)) {
            throw new \InvalidArgumentException('No actions in Slack payload');
        }

        $buttonAction = $actions[0];
        $actionValue = json_decode($buttonAction['value'] ?? '{}', true);

        $response = $actionValue['action'] ?? $buttonAction['action_id'] ?? '';
        $token = $actionValue['token'] ?? '';

        // Get user info
        $user = $payload['user'] ?? [];
        $userId = $user['id'] ?? 'slack_user';
        $userName = $user['name'] ?? $user['username'] ?? $userId;

        if (empty($response) || empty($token)) {
            throw new \InvalidArgumentException('Missing action or token in Slack payload');
        }

        return [
            'response' => $response,
            'token' => $token,
            'user_id' => $userName,
            'message_ts' => $payload['message']['ts'] ?? null,
            'channel_id' => $payload['channel']['id'] ?? null,
            'response_url' => $payload['response_url'] ?? null,
        ];
    }

    /**
     * Update a Slack message after response.
     */
    public function updateCardWithResponse(
        string $messageId,
        string $channelId,
        string $response,
        string $respondedBy
    ): void {
        // To update the message, we need the bot token
        // This would be stored on the ChatConnection
        // For now, we'll use the response_url if available
        Log::info('Slack card update requested', [
            'message_id' => $messageId,
            'channel_id' => $channelId,
            'response' => $response,
            'responded_by' => $respondedBy,
        ]);

        // Message updates would require access to the bot token
        // In a full implementation, we'd retrieve the connection and update the message
    }

    /**
     * Update a message using Slack's response_url (from interactive webhook).
     */
    public function updateMessageViaResponseUrl(
        string $responseUrl,
        ScheduledAction $action,
        string $response,
        string $respondedBy
    ): void {
        $statusText = match ($response) {
            'confirm' => "Confirmed by {$respondedBy}",
            'decline' => "Declined by {$respondedBy}",
            'snooze' => "Snoozed by {$respondedBy}",
            default => "Responded: {$response}",
        };

        $statusEmoji = match ($response) {
            'confirm' => ':white_check_mark:',
            'decline' => ':x:',
            'snooze' => ':zzz:',
            default => ':grey_question:',
        };

        try {
            Http::timeout(10)->post($responseUrl, [
                'replace_original' => true,
                'blocks' => [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => "{$statusEmoji} {$action->name}",
                            'emoji' => true,
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "~{$action->getGateMessage()}~",
                        ],
                    ],
                    [
                        'type' => 'context',
                        'elements' => [
                            [
                                'type' => 'mrkdwn',
                                'text' => "*{$statusText}* at ".now()->format('M j, Y g:i A'),
                            ],
                        ],
                    ],
                ],
            ]);

            Log::info('Slack message updated via response_url', [
                'action_id' => $action->id,
                'response' => $response,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update Slack message', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
