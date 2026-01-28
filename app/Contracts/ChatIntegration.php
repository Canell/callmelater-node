<?php

namespace App\Contracts;

use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use Illuminate\Http\Request;

interface ChatIntegration
{
    /**
     * Get the channel identifier (e.g., 'teams', 'slack').
     */
    public function getChannel(): string;

    /**
     * Send a decision card to a recipient.
     *
     * @return array{message_id: string, channel_id: string}
     */
    public function sendDecisionCard(
        ScheduledAction $action,
        ReminderRecipient $recipient,
        string $responseToken
    ): array;

    /**
     * Update a card after response (strikethrough, show who responded).
     */
    public function updateCardWithResponse(
        string $messageId,
        string $channelId,
        string $response,
        string $respondedBy
    ): void;

    /**
     * Verify incoming webhook signature.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Parse the action response from webhook payload.
     *
     * @return array{response: string, token: string, user_id: string}
     */
    public function parseWebhookPayload(Request $request): array;
}
