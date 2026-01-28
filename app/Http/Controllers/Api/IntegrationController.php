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
            'slack_signing_secret' => ['required_if:provider,slack', 'nullable', 'string', 'max:100'],
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

        $connection = ChatConnection::create([
            'account_id' => $request->user()->account_id,
            'provider' => $validated['provider'],
            'name' => $validated['name'],
            'teams_webhook_url' => $validated['teams_webhook_url'] ?? null,
            'slack_bot_token' => $validated['slack_bot_token'] ?? null,
            'slack_signing_secret' => $validated['slack_signing_secret'] ?? null,
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
                $result = $this->testTeamsConnection($connection);
            } else {
                return response()->json(['message' => 'Slack testing not yet implemented.'], 501);
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
        ];
    }
}
