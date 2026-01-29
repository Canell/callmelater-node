<?php

namespace App\Http\Controllers;

use App\Contracts\ChatIntegration;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Services\Chat\SlackIntegration;
use App\Services\Chat\TeamsIntegration;
use App\Services\CreatorNotificationService;
use App\Services\ResponseProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ChatWebhookController extends Controller
{
    public function __construct(
        private ResponseProcessor $responseProcessor,
        private CreatorNotificationService $creatorNotificationService,
    ) {}

    /**
     * Handle incoming webhook from chat providers (Teams, Slack).
     */
    public function webhook(Request $request, string $provider): Response
    {
        // Handle Slack URL verification challenge
        if ($provider === 'slack' && $request->input('type') === 'url_verification') {
            return response($request->input('challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        $integration = $this->getIntegration($provider);

        if (! $integration) {
            Log::warning('Chat webhook for unknown provider', ['provider' => $provider]);

            return response('Unknown provider', 404);
        }

        // Verify signature/payload
        if (! $integration->verifyWebhookSignature($request)) {
            Log::warning('Chat webhook signature verification failed', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 401);
        }

        // Parse payload
        $payload = $integration->parseWebhookPayload($request);

        // Find recipient by token
        $recipient = ReminderRecipient::query()
            ->where('response_token', $payload['token'])
            ->first();

        if (! $recipient) {
            Log::warning('Chat webhook invalid token', [
                'provider' => $provider,
            ]);

            return response('Invalid token', 404);
        }

        // Check if already responded
        if ($recipient->hasResponded()) {
            Log::info('Chat webhook duplicate response', [
                'provider' => $provider,
                'recipient_id' => $recipient->id,
                'existing_status' => $recipient->status,
            ]);

            return response('Already responded', 200);
        }

        // Get the action
        /** @var ScheduledAction $action */
        $action = $recipient->action;

        // Check token expiry
        if ($action->token_expires_at !== null && $action->token_expires_at->isPast()) {
            Log::info('Chat webhook expired token', [
                'provider' => $provider,
                'action_id' => $action->id,
            ]);

            return response('Token expired', 410);
        }

        // Check if action is still awaiting response
        if ($action->resolution_status !== ScheduledAction::STATUS_AWAITING_RESPONSE) {
            Log::info('Chat webhook action not awaiting response', [
                'provider' => $provider,
                'action_id' => $action->id,
                'status' => $action->resolution_status,
            ]);

            return response('Action not awaiting response', 422);
        }

        // Process the response
        try {
            $this->responseProcessor->process(
                $recipient,
                $action,
                $payload['response'],
                $payload['response'] === 'snooze' ? '1h' : null // Default snooze preset
            );

            // Notify creator if enabled
            $this->creatorNotificationService->notifyCreator(
                $action,
                $payload['response'],
                $recipient
            );

            Log::info('Chat webhook processed successfully', [
                'provider' => $provider,
                'action_id' => $action->id,
                'response' => $payload['response'],
            ]);

            // Update the chat message to show response status
            $this->updateChatMessage($integration, $recipient, $action, $payload);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Chat webhook processing failed', [
                'provider' => $provider,
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);

            return response($e->getMessage(), 422);
        }

        return response('OK', 200);
    }

    /**
     * Update the chat message after processing the response.
     */
    private function updateChatMessage(
        ChatIntegration $integration,
        ReminderRecipient $recipient,
        ScheduledAction $action,
        array $payload
    ): void {
        try {
            // For Slack, use the response_url if available for better UX
            if ($integration instanceof SlackIntegration && ! empty($payload['response_url'])) {
                $integration->updateMessageViaResponseUrl(
                    $payload['response_url'],
                    $action,
                    $payload['response'],
                    $payload['user_id']
                );

                return;
            }

            // Generic card update
            if ($recipient->chat_message_id && $recipient->chat_destination) {
                $integration->updateCardWithResponse(
                    $recipient->chat_message_id,
                    $recipient->chat_destination,
                    $payload['response'],
                    $payload['user_id']
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to update chat card', [
                'provider' => $integration->getChannel(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the integration service for a provider.
     */
    private function getIntegration(string $provider): ?ChatIntegration
    {
        return match ($provider) {
            'teams' => app(TeamsIntegration::class),
            'slack' => app(SlackIntegration::class),
            default => null,
        };
    }
}
