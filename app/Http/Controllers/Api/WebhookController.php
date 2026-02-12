<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\UrlValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(
        private UrlValidator $urlValidator
    ) {}

    /**
     * List all webhooks for the account.
     *
     * GET /api/v1/webhooks
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $webhooks = Webhook::where('account_id', $user->account_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Webhook $webhook) => $this->formatWebhook($webhook));

        return response()->json([
            'data' => $webhooks,
        ]);
    }

    /**
     * Register a new webhook.
     *
     * POST /api/v1/webhooks
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'url' => 'required|url:http,https|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'required|string|in:'.implode(',', Webhook::ALL_EVENTS),
            'secret' => 'nullable|string|max:255',
        ]);

        // Validate URL for security (SSRF prevention)
        try {
            $this->urlValidator->validate($validated['url']);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Invalid webhook URL',
                'errors' => ['url' => [$e->getMessage()]],
            ], 422);
        }

        // Check for duplicate URL
        $existing = Webhook::where('account_id', $user->account_id)
            ->where('url', $validated['url'])
            ->first();

        if ($existing) {
            // Update existing webhook instead of creating duplicate
            $existing->update([
                'name' => $validated['name'] ?? $existing->name,
                'events' => array_unique(array_merge($existing->events, $validated['events'])),
                'secret' => $validated['secret'] ?? $existing->secret,
                'is_active' => true,
            ]);

            return response()->json([
                'data' => $this->formatWebhook($existing->fresh()),
                'message' => 'Webhook updated',
            ]);
        }

        $webhook = Webhook::create([
            'account_id' => $user->account_id,
            'created_by_user_id' => $user->id,
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => $validated['secret'] ?? Str::random(32),
            'is_active' => true,
        ]);

        return response()->json([
            'data' => $this->formatWebhook($webhook),
            'message' => 'Webhook registered',
        ], 201);
    }

    /**
     * Get a specific webhook.
     *
     * GET /api/v1/webhooks/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $webhook = Webhook::where('account_id', $user->account_id)->find($id);

        if (! $webhook) {
            return response()->json(['message' => 'Webhook not found'], 404);
        }

        return response()->json([
            'data' => $this->formatWebhook($webhook),
        ]);
    }

    /**
     * Update a webhook.
     *
     * PATCH /api/v1/webhooks/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $webhook = Webhook::where('account_id', $user->account_id)->find($id);

        if (! $webhook) {
            return response()->json(['message' => 'Webhook not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'url' => 'sometimes|url:http,https|max:2048',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'required|string|in:'.implode(',', Webhook::ALL_EVENTS),
            'secret' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        // Validate URL if provided
        if (isset($validated['url'])) {
            try {
                $this->urlValidator->validate($validated['url']);
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'message' => 'Invalid webhook URL',
                    'errors' => ['url' => [$e->getMessage()]],
                ], 422);
            }
        }

        $webhook->update($validated);

        return response()->json([
            'data' => $this->formatWebhook($webhook->fresh()),
            'message' => 'Webhook updated',
        ]);
    }

    /**
     * Delete a webhook.
     *
     * DELETE /api/v1/webhooks/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $webhook = Webhook::where('account_id', $user->account_id)->find($id);

        if (! $webhook) {
            return response()->json(['message' => 'Webhook not found'], 404);
        }

        $webhook->delete();

        return response()->json([
            'message' => 'Webhook deleted',
        ]);
    }

    /**
     * Format webhook for API response.
     *
     * @return array<string, mixed>
     */
    private function formatWebhook(Webhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'is_active' => $webhook->is_active,
            'created_at' => $webhook->created_at->toIso8601String(),
            'updated_at' => $webhook->updated_at->toIso8601String(),
        ];
    }
}
