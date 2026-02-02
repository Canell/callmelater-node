<?php

namespace App\Jobs;

use App\Contracts\ChatIntegration;
use App\Mail\ReminderMail;
use App\Models\BlockedRecipient;
use App\Models\ChatConnection;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\ScheduledAction;
use App\Models\Contact;
use App\Models\User;
use App\Services\BrevoService;
use App\Services\Chat\SlackIntegration;
use App\Services\Chat\TeamsIntegration;
use App\Services\QuotaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DeliverReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ScheduledAction $action
    ) {}

    public function handle(BrevoService $brevoService, QuotaService $quotaService): void
    {
        // CRITICAL: Verify action is still in EXECUTING state
        // This guards against cancellation race conditions
        $this->action->refresh();
        if (! $this->action->isExecuting()) {
            Log::info('Gate notification skipped - no longer in executing state', [
                'action_id' => $this->action->id,
                'status' => $this->action->resolution_status,
            ]);

            return;
        }

        // Get recipients from gate configuration
        $recipients = $this->action->getGateRecipients();
        $configuredChannels = $this->action->getGateChannels();
        $hasChatChannel = ! empty(array_intersect($configuredChannels, ['teams', 'slack']));

        // Handle Teams/Slack-only mode (no email/phone recipients)
        if (count($recipients) === 0 && $hasChatChannel) {
            $this->sendChatOnly($configuredChannels, $quotaService);

            return;
        }

        if (count($recipients) === 0) {
            Log::error('No recipients configured for gated action', ['action_id' => $this->action->id]);
            $this->action->markAsFailed('No recipients configured');

            return;
        }

        // Parse timeout to days
        $tokenExpiryDays = ScheduledAction::parseTimeoutToDays($this->action->getGateTimeout());

        $sentCount = 0;
        $blockedCount = 0;
        $channelsUsed = [];

        // Create recipient records with response tokens and send notifications
        foreach ($recipients as $recipient) {
            // Parse and resolve the recipient URI
            $parsed = $this->parseRecipientUri($recipient);
            $resolved = $this->resolveRecipient($parsed);

            // Handle channel recipients separately
            if ($resolved['is_channel']) {
                $channelId = $resolved['channel_id'];
                $connection = ChatConnection::where('id', $channelId)
                    ->where('account_id', $this->action->account_id)
                    ->where('is_active', true)
                    ->first();

                if ($connection) {
                    $provider = $connection->provider;
                    $chatSent = $this->sendChatToConnection($connection);
                    if ($chatSent) {
                        $channelsUsed[$provider] = true;
                        $sentCount++;
                    }
                } else {
                    Log::warning('Chat connection not found or inactive', [
                        'action_id' => $this->action->id,
                        'channel_id' => $channelId,
                    ]);
                }

                continue;
            }

            // Get resolved contact info
            $resolvedContact = $resolved['contact'];
            $contactId = $resolved['contact_id'];

            if (! $resolvedContact) {
                Log::warning('Could not resolve recipient contact info', [
                    'action_id' => $this->action->id,
                    'recipient' => $recipient,
                ]);

                continue;
            }

            // Determine if it's an email or phone number
            $isPhone = $this->isPhoneNumber($resolvedContact);
            $isEmail = filter_var($resolvedContact, FILTER_VALIDATE_EMAIL) !== false;

            // Check if recipient is blocked
            if (BlockedRecipient::isBlocked($resolvedContact)) {
                Log::info('Recipient blocked, skipping', [
                    'action_id' => $this->action->id,
                    'recipient' => $resolvedContact,
                ]);
                $blockedCount++;

                continue;
            }

            // Use firstOrCreate to handle retries/re-dispatches gracefully
            $recipientRecord = ReminderRecipient::firstOrCreate(
                [
                    'action_id' => $this->action->id,
                    'email' => $resolvedContact,
                ],
                [
                    'contact_id' => $contactId,
                    'status' => ReminderRecipient::STATUS_PENDING,
                    'response_token' => Str::random(20),
                ]
            );

            // Skip if already processed (not pending)
            if ($recipientRecord->status !== ReminderRecipient::STATUS_PENDING) {
                continue;
            }

            // Get configured channels for chat integrations (Teams/Slack)
            // Email/SMS are auto-detected from recipient type
            $configuredChannels = $this->action->getGateChannels();

            // Auto-detect channel from recipient type and send
            if ($isEmail) {
                // Always send email for email recipients (auto-detected)
                $this->sendEmail($recipientRecord);
                $channelsUsed['email'] = true;

                // Also send to chat channels if explicitly configured
                foreach (['teams', 'slack'] as $chatProvider) {
                    if (in_array($chatProvider, $configuredChannels, true)) {
                        $chatSent = $this->sendChat($recipientRecord, $chatProvider);
                        if ($chatSent) {
                            $channelsUsed[$chatProvider] = true;
                        }
                    }
                }

                $recipientRecord->update(['status' => ReminderRecipient::STATUS_SENT]);
                $sentCount++;
            } elseif ($isPhone) {
                // Check SMS quota before sending
                $account = $this->action->account;
                if ($account && ! $quotaService->canSendSms($account)) {
                    Log::warning('SMS quota exceeded, skipping SMS delivery', [
                        'action_id' => $this->action->id,
                        'recipient' => $recipient,
                    ]);

                    continue;
                }

                $this->sendSms($recipientRecord, $brevoService);
                $recipientRecord->update(['status' => ReminderRecipient::STATUS_SENT]);
                $sentCount++;
                $channelsUsed['sms'] = true;

                // Record SMS usage
                if ($account) {
                    $quotaService->recordSmsSent($account);
                }
            }
        }

        // Build notes for the event
        $channels = array_keys($channelsUsed);
        $notes = "Sent to {$sentCount} recipient(s) via ".implode(', ', $channels);
        if ($blockedCount > 0) {
            $notes .= ", {$blockedCount} blocked";
        }

        // Record the sent event
        ReminderEvent::create([
            'reminder_id' => $this->action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
            'captured_timezone' => $this->action->timezone,
            'notes' => $notes,
        ]);

        // Mark as awaiting response (uses model's state machine)
        $this->action->markAsAwaitingResponse($tokenExpiryDays);

        Log::info('Gate notification delivered', [
            'action_id' => $this->action->id,
            'mode' => $this->action->mode,
            'recipients' => count($recipients),
            'sent' => $sentCount,
            'blocked' => $blockedCount,
            'channels' => $channels,
            'has_request' => $this->action->hasRequest(),
        ]);
    }

    private function sendEmail(ReminderRecipient $recipient): void
    {
        try {
            Mail::to($recipient->email)->send(new ReminderMail($this->action, $recipient));

            Log::info('Gate notification email sent', [
                'action_id' => $this->action->id,
                'recipient' => $recipient->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send gate notification email', [
                'action_id' => $this->action->id,
                'recipient' => $recipient->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendSms(ReminderRecipient $recipient, BrevoService $brevoService): void
    {
        $baseUrl = config('app.url');
        $token = $recipient->response_token;

        // Short URL format for SMS: /r/{token}
        $responseUrl = "{$baseUrl}/r/{$token}";

        $brevoService->sendReminderSms(
            $recipient->email, // In this case, it's a phone number stored in the email field
            $this->action->name,
            $this->action->getGateMessage(),
            $responseUrl
        );
    }

    /**
     * Handle chat-only mode (no email/phone recipients, only Teams/Slack).
     *
     * @param  array<string>  $channels
     */
    private function sendChatOnly(array $channels, QuotaService $quotaService): void
    {
        $tokenExpiryDays = ScheduledAction::parseTimeoutToDays($this->action->getGateTimeout());
        $sentCount = 0;

        // Get specific integration IDs if provided
        $integrationIds = $this->action->gate['integration_ids'] ?? [];

        foreach (['teams', 'slack'] as $provider) {
            if (! in_array($provider, $channels, true)) {
                continue;
            }

            // Get connections - either specific IDs or all active for this provider
            $query = ChatConnection::where('account_id', $this->action->account_id)
                ->where('provider', $provider)
                ->where('is_active', true);

            if (! empty($integrationIds)) {
                $query->whereIn('id', $integrationIds);
            }

            $connections = $query->get();

            foreach ($connections as $connection) {
                $webhookUrl = $connection->teams_webhook_url ?? $connection->slack_bot_token;
                if (! $webhookUrl) {
                    continue;
                }

                // Create a recipient record for this chat channel
                $recipientRecord = ReminderRecipient::firstOrCreate(
                    [
                        'action_id' => $this->action->id,
                        'email' => "{$provider}:{$connection->id}", // Unique identifier per connection
                    ],
                    [
                        'chat_provider' => $provider,
                        'chat_destination' => $webhookUrl,
                        'slack_channel_id' => $connection->slack_channel_id,
                        'status' => ReminderRecipient::STATUS_PENDING,
                        'response_token' => Str::random(20),
                    ]
                );

                if ($recipientRecord->status !== ReminderRecipient::STATUS_PENDING) {
                    continue;
                }

                // Get the integration service
                $integration = $this->getChatIntegration($provider);
                if (! $integration) {
                    continue;
                }

                try {
                    $result = $integration->sendDecisionCard(
                        $this->action,
                        $recipientRecord,
                        $recipientRecord->response_token
                    );

                    $recipientRecord->update([
                        'status' => ReminderRecipient::STATUS_SENT,
                        'chat_message_id' => $result['message_id'],
                    ]);

                    $sentCount++;

                    Log::info('Chat reminder sent', [
                        'action_id' => $this->action->id,
                        'provider' => $provider,
                        'connection_id' => $connection->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send chat reminder', [
                        'action_id' => $this->action->id,
                        'provider' => $provider,
                        'error' => $e->getMessage(),
                    ]);
                    // Leave as pending - don't have a FAILED status
                }
            }
        }

        if ($sentCount === 0) {
            Log::error('No chat messages sent for gated action', ['action_id' => $this->action->id]);
            $this->action->markAsFailed('Failed to send to any chat channel');

            return;
        }

        // Mark as awaiting response
        $this->action->markAsAwaitingResponse($tokenExpiryDays);

        // Record the sent event
        ReminderEvent::create([
            'reminder_id' => $this->action->id,
            'event_type' => ReminderEvent::TYPE_SENT,
            'captured_timezone' => $this->action->timezone,
            'notes' => "Sent to {$sentCount} chat channel(s): ".implode(', ', $channels),
        ]);

        Log::info('Chat-only reminder delivered', [
            'action_id' => $this->action->id,
            'sent_count' => $sentCount,
        ]);
    }

    /**
     * Send reminder via chat integration (Teams/Slack).
     *
     * @return bool Whether the message was sent successfully
     */
    private function sendChat(ReminderRecipient $recipient, string $provider): bool
    {
        try {
            // Get specific integration IDs if provided
            $integrationIds = $this->action->gate['integration_ids'] ?? [];

            // Get the chat connections for this account
            $query = ChatConnection::where('account_id', $this->action->account_id)
                ->where('provider', $provider)
                ->where('is_active', true);

            if (! empty($integrationIds)) {
                $query->whereIn('id', $integrationIds);
            }

            $connections = $query->get();

            if ($connections->isEmpty()) {
                Log::warning('No active chat connection found', [
                    'action_id' => $this->action->id,
                    'provider' => $provider,
                ]);

                return false;
            }

            $sentAny = false;

            // Send to all matching connections
            foreach ($connections as $connection) {
                // Get the webhook URL for Teams or bot token for Slack
                $destination = $connection->teams_webhook_url ?? $connection->slack_bot_token;
                if (! $destination) {
                    Log::warning('No webhook/token configured for chat connection', [
                        'action_id' => $this->action->id,
                        'provider' => $provider,
                        'connection_id' => $connection->id,
                    ]);

                    continue;
                }

                // Create a separate recipient record for each chat connection
                $chatRecipient = ReminderRecipient::firstOrCreate(
                    [
                        'action_id' => $this->action->id,
                        'email' => "{$provider}:{$connection->id}",
                    ],
                    [
                        'chat_provider' => $provider,
                        'chat_destination' => $destination,
                        'slack_channel_id' => $connection->slack_channel_id,
                        'status' => ReminderRecipient::STATUS_PENDING,
                        'response_token' => Str::random(20),
                    ]
                );

                if ($chatRecipient->status !== ReminderRecipient::STATUS_PENDING) {
                    continue;
                }

                // Get the integration service
                $integration = $this->getChatIntegration($provider);
                if (! $integration) {
                    continue;
                }

                // Send the decision card
                $result = $integration->sendDecisionCard(
                    $this->action,
                    $chatRecipient,
                    $chatRecipient->response_token
                );

                // Update the chat recipient record
                $chatRecipient->update([
                    'status' => ReminderRecipient::STATUS_SENT,
                    'chat_message_id' => $result['message_id'],
                ]);

                Log::info('Chat notification sent', [
                    'action_id' => $this->action->id,
                    'provider' => $provider,
                    'connection_id' => $connection->id,
                    'connection_name' => $connection->name,
                    'message_id' => $result['message_id'],
                ]);

                $sentAny = true;
            }

            return $sentAny;
        } catch (\Exception $e) {
            Log::error('Failed to send chat notification', [
                'action_id' => $this->action->id,
                'provider' => $provider,
                'recipient' => $recipient->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the chat integration service for a provider.
     */
    private function getChatIntegration(string $provider): ?ChatIntegration
    {
        return match ($provider) {
            'teams' => app(TeamsIntegration::class),
            'slack' => app(SlackIntegration::class),
            default => null,
        };
    }

    /**
     * Send a chat message to a specific connection.
     *
     * @return bool Whether the message was sent successfully
     */
    private function sendChatToConnection(ChatConnection $connection): bool
    {
        $provider = $connection->provider;
        $destination = $connection->teams_webhook_url ?? $connection->slack_bot_token;

        if (! $destination) {
            Log::warning('No webhook/token configured for chat connection', [
                'action_id' => $this->action->id,
                'provider' => $provider,
                'connection_id' => $connection->id,
            ]);

            return false;
        }

        // Create a recipient record for this chat channel
        $chatRecipient = ReminderRecipient::firstOrCreate(
            [
                'action_id' => $this->action->id,
                'email' => "{$provider}:{$connection->id}",
            ],
            [
                'chat_provider' => $provider,
                'chat_destination' => $destination,
                'slack_channel_id' => $connection->slack_channel_id,
                'status' => ReminderRecipient::STATUS_PENDING,
                'response_token' => Str::random(20),
            ]
        );

        if ($chatRecipient->status !== ReminderRecipient::STATUS_PENDING) {
            return false; // Already sent
        }

        $integration = $this->getChatIntegration($provider);
        if (! $integration) {
            return false;
        }

        try {
            $result = $integration->sendDecisionCard(
                $this->action,
                $chatRecipient,
                $chatRecipient->response_token
            );

            $chatRecipient->update([
                'status' => ReminderRecipient::STATUS_SENT,
                'chat_message_id' => $result['message_id'],
            ]);

            Log::info('Chat notification sent to channel', [
                'action_id' => $this->action->id,
                'provider' => $provider,
                'connection_id' => $connection->id,
                'connection_name' => $connection->name,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send chat notification to channel', [
                'action_id' => $this->action->id,
                'provider' => $provider,
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function isPhoneNumber(string $value): bool
    {
        // Simple check for phone numbers (starts with + or contains only digits, spaces, dashes)
        return preg_match('/^\+?[\d\s\-\(\)]+$/', $value) === 1 && strlen(preg_replace('/\D/', '', $value)) >= 10;
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /**
     * Parse a recipient URI into its components.
     *
     * Supported formats:
     * - user:{id}:email - Workspace member's email
     * - user:{id}:phone - Workspace member's phone
     * - contact:{uuid}:email - Contact's email
     * - contact:{uuid}:phone - Contact's phone
     * - member:{uuid}:email - Contact's email (legacy alias)
     * - member:{uuid}:phone - Contact's phone (legacy alias)
     * - channel:{uuid} - Chat channel
     * - email:{address} - Direct email
     * - phone:{number} - Direct phone
     * - {uuid} - Legacy contact ID
     * - {email} - Legacy email address
     * - {phone} - Legacy phone number
     *
     * @return array{type: string, id: string|null, contact_type: string|null, value: string|null}
     */
    private function parseRecipientUri(string $recipient): array
    {
        // New URI format: type:value or type:id:subtype
        if (str_contains($recipient, ':')) {
            $parts = explode(':', $recipient, 3);
            $type = $parts[0];

            switch ($type) {
                case 'contact':
                case 'member': // Legacy alias for contact
                    $contactId = $parts[1] ?? null;
                    $contactType = $parts[2] ?? 'email'; // Default to email

                    return [
                        'type' => 'contact',
                        'id' => $contactId,
                        'contact_type' => $contactType,
                        'value' => null,
                    ];

                case 'user':
                    $userId = $parts[1] ?? null;
                    $contactType = $parts[2] ?? 'email'; // Default to email

                    return [
                        'type' => 'user',
                        'id' => $userId,
                        'contact_type' => $contactType,
                        'value' => null,
                    ];

                case 'channel':
                    return [
                        'type' => 'channel',
                        'id' => $parts[1] ?? null,
                        'contact_type' => null,
                        'value' => null,
                    ];

                case 'email':
                    // Rejoin in case email contains colons (unlikely but handle it)
                    $email = implode(':', array_slice($parts, 1));

                    return [
                        'type' => 'email',
                        'id' => null,
                        'contact_type' => 'email',
                        'value' => $email,
                    ];

                case 'phone':
                    return [
                        'type' => 'phone',
                        'id' => null,
                        'contact_type' => 'phone',
                        'value' => $parts[1] ?? null,
                    ];
            }
        }

        // Legacy format: UUID (contact)
        if ($this->isUuid($recipient)) {
            return [
                'type' => 'contact',
                'id' => $recipient,
                'contact_type' => 'email', // Default to email for legacy
                'value' => null,
            ];
        }

        // Legacy format: email
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false) {
            return [
                'type' => 'email',
                'id' => null,
                'contact_type' => 'email',
                'value' => $recipient,
            ];
        }

        // Legacy format: phone
        if ($this->isPhoneNumber($recipient)) {
            return [
                'type' => 'phone',
                'id' => null,
                'contact_type' => 'phone',
                'value' => $recipient,
            ];
        }

        // Unknown format, treat as email
        return [
            'type' => 'email',
            'id' => null,
            'contact_type' => 'email',
            'value' => $recipient,
        ];
    }

    /**
     * Resolve a parsed recipient to actual contact information.
     *
     * @param  array{type: string, id: string|null, contact_type: string|null, value: string|null}  $parsed
     * @return array{contact: string|null, contact_id: string|null, is_channel: bool, channel_id: string|null}
     */
    private function resolveRecipient(array $parsed): array
    {
        $result = [
            'contact' => null,
            'contact_id' => null,
            'is_channel' => false,
            'channel_id' => null,
        ];

        switch ($parsed['type']) {
            case 'contact':
                $contactRecord = Contact::where('id', $parsed['id'])
                    ->where('account_id', $this->action->account_id)
                    ->first();

                if ($contactRecord) {
                    $result['contact_id'] = $contactRecord->id;
                    // Get preferred contact type
                    if ($parsed['contact_type'] === 'phone' && $contactRecord->phone) {
                        $result['contact'] = $contactRecord->phone;
                    } elseif ($contactRecord->email) {
                        $result['contact'] = $contactRecord->email;
                    } elseif ($contactRecord->phone) {
                        $result['contact'] = $contactRecord->phone;
                    }
                }
                break;

            case 'user':
                $user = User::where('id', $parsed['id'])
                    ->where('account_id', $this->action->account_id)
                    ->first();

                if ($user) {
                    // Get preferred contact type
                    if ($parsed['contact_type'] === 'phone' && $user->phone) {
                        $result['contact'] = $user->phone;
                    } elseif ($user->email) {
                        $result['contact'] = $user->email;
                    } elseif ($user->phone) {
                        $result['contact'] = $user->phone;
                    }
                }
                break;

            case 'channel':
                $result['is_channel'] = true;
                $result['channel_id'] = $parsed['id'];
                break;

            case 'email':
            case 'phone':
                $result['contact'] = $parsed['value'];
                break;
        }

        return $result;
    }
}
