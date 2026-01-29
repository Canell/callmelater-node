<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    /**
     * List all chat connections for the account.
     */
    public function index(Request $request): JsonResponse
    {
        $connections = ChatConnection::where('account_id', $request->user()->account_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ChatConnection $c) => $this->formatConnection($c));

        return response()->json([
            'data' => $connections,
            'can_create' => $this->canCreateIntegration($request),
        ]);
    }

    /**
     * Create a new chat connection.
     */
    public function store(Request $request): JsonResponse
    {
        // Check plan limits
        if (! $this->canCreateIntegration($request)) {
            return response()->json([
                'message' => 'Chat integrations require a Pro or Business plan.',
            ], 403);
        }

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(['teams', 'slack'])],
            'name' => ['required', 'string', 'max:255'],
            'teams_webhook_url' => ['required_if:provider,teams', 'nullable', 'url', 'max:2000'],
            'slack_bot_token' => ['required_if:provider,slack', 'nullable', 'string', 'max:500'],
            'slack_signing_secret' => ['nullable', 'string', 'max:100'],
            'slack_channel_id' => ['required_if:provider,slack', 'nullable', 'string', 'max:50'],
            'slack_channel_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Validate Teams webhook URL format
        // Accept both legacy connector URLs (webhook.office.com, outlook.office.com)
        // and new Workflows URLs (*.logic.azure.com)
        if ($validated['provider'] === 'teams' && ! empty($validated['teams_webhook_url'])) {
            $url = $validated['teams_webhook_url'];
            $isValidTeamsUrl = str_contains($url, 'webhook.office.com')
                || str_contains($url, 'outlook.office.com')
                || str_contains($url, '.logic.azure.com');

            if (! $isValidTeamsUrl) {
                return response()->json([
                    'message' => 'Invalid Teams webhook URL. Please use an Incoming Webhook URL from Microsoft Teams or a Workflows webhook URL.',
                ], 422);
            }
        }

        // Validate Slack bot token by testing the API
        if ($validated['provider'] === 'slack' && ! empty($validated['slack_bot_token'])) {
            $testResponse = \Http::withToken($validated['slack_bot_token'])
                ->timeout(10)
                ->get('https://slack.com/api/auth.test');

            $testData = $testResponse->json();
            if (! ($testData['ok'] ?? false)) {
                return response()->json([
                    'message' => 'Invalid Slack bot token. Please check your token and try again.',
                ], 422);
            }
        }

        $connection = ChatConnection::create([
            'account_id' => $request->user()->account_id,
            'provider' => $validated['provider'],
            'name' => $validated['name'],
            'teams_webhook_url' => $validated['teams_webhook_url'] ?? null,
            'slack_bot_token' => $validated['slack_bot_token'] ?? null,
            'slack_signing_secret' => $validated['slack_signing_secret'] ?? null,
            'slack_channel_id' => $validated['slack_channel_id'] ?? null,
            'slack_channel_name' => $validated['slack_channel_name'] ?? null,
            'is_active' => true,
            'connected_at' => now(),
        ]);

        Log::info('Chat connection created', [
            'connection_id' => $connection->id,
            'provider' => $connection->provider,
            'account_id' => $connection->account_id,
        ]);

        return response()->json([
            'message' => 'Connection created successfully.',
            'data' => $this->formatConnection($connection),
        ], 201);
    }

    /**
     * Update a chat connection.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $connection = ChatConnection::where('account_id', $request->user()->account_id)
            ->where('id', $id)
            ->first();

        if (! $connection) {
            return response()->json(['error' => 'not_found', 'message' => 'Connection not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'teams_webhook_url' => ['nullable', 'url', 'max:2000'],
            'slack_bot_token' => ['nullable', 'string', 'max:500'],
            'slack_signing_secret' => ['nullable', 'string', 'max:100'],
            'slack_channel_id' => ['nullable', 'string', 'max:50'],
            'slack_channel_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Validate Teams webhook URL format if provided
        if ($connection->isTeams() && ! empty($validated['teams_webhook_url'])) {
            $url = $validated['teams_webhook_url'];
            $isValidTeamsUrl = str_contains($url, 'webhook.office.com')
                || str_contains($url, 'outlook.office.com')
                || str_contains($url, '.logic.azure.com');

            if (! $isValidTeamsUrl) {
                return response()->json([
                    'message' => 'Invalid Teams webhook URL.',
                ], 422);
            }
        }

        // Validate Slack bot token if provided
        if ($connection->isSlack() && ! empty($validated['slack_bot_token'])) {
            $testResponse = \Http::withToken($validated['slack_bot_token'])
                ->timeout(10)
                ->get('https://slack.com/api/auth.test');

            $testData = $testResponse->json();
            if (! ($testData['ok'] ?? false)) {
                return response()->json([
                    'message' => 'Invalid Slack bot token.',
                ], 422);
            }
        }

        // Only update fields that were provided
        $updateData = [];
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if ($connection->isTeams() && isset($validated['teams_webhook_url'])) {
            $updateData['teams_webhook_url'] = $validated['teams_webhook_url'];
        }
        if ($connection->isSlack()) {
            if (isset($validated['slack_bot_token'])) {
                $updateData['slack_bot_token'] = $validated['slack_bot_token'];
            }
            if (isset($validated['slack_channel_id'])) {
                $updateData['slack_channel_id'] = $validated['slack_channel_id'];
            }
            if (isset($validated['slack_channel_name'])) {
                $updateData['slack_channel_name'] = $validated['slack_channel_name'];
            }
        }

        if (! empty($updateData)) {
            $connection->update($updateData);
        }

        Log::info('Chat connection updated', [
            'connection_id' => $connection->id,
            'provider' => $connection->provider,
            'account_id' => $connection->account_id,
        ]);

        return response()->json([
            'message' => 'Connection updated successfully.',
            'data' => $this->formatConnection($connection),
        ]);
    }

    /**
     * Delete a chat connection.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $connection = ChatConnection::where('account_id', $request->user()->account_id)
            ->where('id', $id)
            ->first();

        if (! $connection) {
            return response()->json(['error' => 'not_found', 'message' => 'Connection not found.'], 404);
        }

        $connection->delete();

        Log::info('Chat connection deleted', [
            'connection_id' => $id,
            'provider' => $connection->provider,
            'account_id' => $connection->account_id,
        ]);

        return response()->json(['message' => 'Connection deleted.']);
    }

    /**
     * Test a chat connection by sending a test message.
     */
    public function test(Request $request, string $id): JsonResponse
    {
        $connection = ChatConnection::where('account_id', $request->user()->account_id)
            ->where('id', $id)
            ->first();

        if (! $connection) {
            return response()->json(['error' => 'not_found', 'message' => 'Connection not found.'], 404);
        }

        try {
            if ($connection->isTeams()) {
                $this->testTeamsConnection($connection);
            } elseif ($connection->isSlack()) {
                $this->testSlackConnection($connection);
            } else {
                return response()->json(['message' => 'Unknown provider.'], 501);
            }

            return response()->json([
                'success' => true,
                'message' => 'Test message sent successfully.',
            ]);
        } catch (\Exception $e) {
            Log::warning('Chat connection test failed', [
                'connection_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle a connection's active status.
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        $connection = ChatConnection::where('account_id', $request->user()->account_id)
            ->where('id', $id)
            ->first();

        if (! $connection) {
            return response()->json(['error' => 'not_found', 'message' => 'Connection not found.'], 404);
        }

        $connection->update(['is_active' => ! $connection->is_active]);

        return response()->json([
            'message' => $connection->is_active ? 'Connection enabled.' : 'Connection disabled.',
            'is_active' => $connection->is_active,
        ]);
    }

    /**
     * Test Teams connection by sending a simple card.
     */
    private function testTeamsConnection(ChatConnection $connection): bool
    {
        $response = \Http::timeout(10)->post($connection->teams_webhook_url, [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'body' => [
                            [
                                'type' => 'TextBlock',
                                'text' => 'Test Connection Successful',
                                'weight' => 'bolder',
                                'size' => 'large',
                            ],
                            [
                                'type' => 'TextBlock',
                                'text' => 'Your CallMeLater integration is working correctly. You will receive reminder notifications in this channel.',
                                'wrap' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new \Exception('Teams returned status '.$response->status());
        }

        return true;
    }

    /**
     * Test Slack connection by sending a simple message.
     */
    private function testSlackConnection(ChatConnection $connection): bool
    {
        if (empty($connection->slack_bot_token) || empty($connection->slack_channel_id)) {
            throw new \Exception('Slack bot token and channel are required');
        }

        $response = \Http::withToken($connection->slack_bot_token)
            ->timeout(10)
            ->post('https://slack.com/api/chat.postMessage', [
                'channel' => $connection->slack_channel_id,
                'text' => 'Test Connection Successful',
                'blocks' => [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => ':white_check_mark: Test Connection Successful',
                            'emoji' => true,
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => 'Your CallMeLater integration is working correctly. You will receive reminder notifications in this channel.',
                        ],
                    ],
                ],
            ]);

        $data = $response->json();
        if (! ($data['ok'] ?? false)) {
            $error = $data['error'] ?? 'Unknown error';
            throw new \Exception("Slack error: {$error}");
        }

        return true;
    }

    /**
     * Fetch Slack channels for a bot token (used during setup).
     */
    public function slackChannels(Request $request): JsonResponse
    {
        if (! $this->canCreateIntegration($request)) {
            return response()->json(['message' => 'Chat integrations require a Pro or Business plan.'], 403);
        }

        $validated = $request->validate([
            'bot_token' => ['required', 'string'],
        ]);

        try {
            $response = \Http::withToken($validated['bot_token'])
                ->timeout(10)
                ->get('https://slack.com/api/conversations.list', [
                    'types' => 'public_channel,private_channel',
                    'exclude_archived' => true,
                    'limit' => 200,
                ]);

            $data = $response->json();
            if (! ($data['ok'] ?? false)) {
                return response()->json([
                    'message' => 'Failed to fetch channels: '.($data['error'] ?? 'Unknown error'),
                ], 422);
            }

            $channels = collect($data['channels'] ?? [])
                ->map(fn ($ch) => [
                    'id' => $ch['id'],
                    'name' => $ch['name'],
                    'is_private' => $ch['is_private'] ?? false,
                ])
                ->sortBy('name')
                ->values();

            return response()->json(['channels' => $channels]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch channels: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check if the user can create chat integrations based on their plan.
     */
    private function canCreateIntegration(Request $request): bool
    {
        $account = $request->user()->account;
        $limits = $account->getPlanLimits();

        return $limits['chat_integrations'] ?? false;
    }

    /**
     * Format a connection for API response.
     */
    private function formatConnection(ChatConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'provider' => $connection->provider,
            'name' => $connection->name,
            'is_active' => $connection->is_active,
            'connected_at' => $connection->connected_at?->toIso8601String(),
            // Don't expose full webhook URL or tokens
            'has_webhook_url' => ! empty($connection->teams_webhook_url),
            'has_bot_token' => ! empty($connection->slack_bot_token),
            'slack_channel_name' => $connection->slack_channel_name,
        ];
    }
}
