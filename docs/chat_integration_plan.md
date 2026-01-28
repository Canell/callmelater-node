# Plan: Chat Integrations (Teams + Slack) & Creator Notifications

## Summary

Add Microsoft Teams and Slack as notification channels for gated reminders with interactive button responses. Plus a new feature to notify action creators when someone responds.

**Key insight:** These platforms are "just another human decision surface" - same role as email, just richer UI. No user sync, no channel discovery, no bot conversations.

**Features:**
1. **Teams as notification channel** (Phase 1) - Send gated reminders via Adaptive Cards
2. **Slack as notification channel** (Phase 2) - Send gated reminders via Block Kit
3. **Interactive responses** - Confirm/Decline/Snooze via buttons on both platforms
4. **Creator notifications** - Notify action creator when someone responds (all channels)

**Plan availability:** Pro and Business tiers only

---

## Architecture: Unified Chat Integration

### Interface: `app/Contracts/ChatIntegration.php`

```php
interface ChatIntegration
{
    /**
     * Get the channel identifier (e.g., 'teams', 'slack').
     */
    public function getChannel(): string;

    /**
     * Send a decision card to a recipient.
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
     * @return array{response: string, token: string, user_id: string}
     */
    public function parseWebhookPayload(Request $request): array;
}
```

This interface allows both Teams and Slack to share:
- The same delivery logic in `ReminderService`
- The same response processing in `ResponseProcessor`
- The same webhook controller pattern

---

## Phase 1: Microsoft Teams Integration

### What We're Building (Minimal Scope)

| Component | Include | Exclude |
|-----------|---------|---------|
| Teams App | Incoming webhooks, Adaptive Cards, action buttons | Tabs, message extensions, channel discovery |
| User mapping | User provides Teams user ID or webhook URL | Org sync, user provisioning |
| Delivery | POST to webhook/user | Bot conversations, threads |
| Security | JWT validation, per-action tokens | Complex permissions |

### Database Schema

#### Migration 1: `create_chat_connections_table.php`

```php
Schema::create('chat_connections', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('account_id');

    $table->string('provider'); // 'teams' or 'slack'
    $table->string('name'); // User-friendly name (e.g., "Engineering Team")

    // Teams-specific
    $table->string('teams_tenant_id')->nullable();
    $table->string('teams_webhook_url')->nullable(); // Incoming webhook URL

    // Slack-specific (for Phase 2)
    $table->string('slack_team_id')->nullable();
    $table->string('slack_bot_token')->nullable(); // encrypted
    $table->string('slack_signing_secret')->nullable(); // encrypted

    $table->boolean('is_active')->default(true);
    $table->timestamp('connected_at');
    $table->timestamps();

    $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
    $table->index(['account_id', 'provider']);
});
```

#### Migration 2: `add_chat_fields_to_reminder_recipients.php`

```php
Schema::table('reminder_recipients', function (Blueprint $table) {
    $table->string('chat_provider')->nullable()->after('phone'); // 'teams' or 'slack'
    $table->string('chat_destination')->nullable()->after('chat_provider'); // User ID, channel ID, or webhook URL
    $table->string('chat_message_id')->nullable()->after('chat_destination'); // For updating messages
});
```

#### Migration 3: `add_creator_notification_to_scheduled_actions.php`

```php
Schema::table('scheduled_actions', function (Blueprint $table) {
    $table->boolean('notify_creator_on_response')->default(false)->after('gate');
});
```

### Teams Implementation

#### Model: `app/Models/ChatConnection.php`

```php
class ChatConnection extends Model
{
    use HasUuids;

    protected $fillable = [
        'account_id', 'provider', 'name',
        'teams_tenant_id', 'teams_webhook_url',
        'slack_team_id', 'slack_bot_token', 'slack_signing_secret',
        'is_active', 'connected_at',
    ];

    protected $casts = [
        'slack_bot_token' => 'encrypted',
        'slack_signing_secret' => 'encrypted',
        'is_active' => 'boolean',
        'connected_at' => 'datetime',
    ];

    public function account(): BelongsTo;

    public function scopeTeams($query) { return $query->where('provider', 'teams'); }
    public function scopeSlack($query) { return $query->where('provider', 'slack'); }
    public function scopeActive($query) { return $query->where('is_active', true); }
}
```

#### Service: `app/Services/Chat/TeamsIntegration.php`

```php
class TeamsIntegration implements ChatIntegration
{
    public function getChannel(): string
    {
        return 'teams';
    }

    public function sendDecisionCard(
        ScheduledAction $action,
        ReminderRecipient $recipient,
        string $responseToken
    ): array {
        $webhookUrl = $recipient->chat_destination;

        $card = $this->buildAdaptiveCard($action, $responseToken);

        $response = Http::post($webhookUrl, [
            'type' => 'message',
            'attachments' => [[
                'contentType' => 'application/vnd.microsoft.card.adaptive',
                'content' => $card,
            ]],
        ]);

        return [
            'message_id' => $response->header('x-ms-activity-id') ?? Str::uuid(),
            'channel_id' => $webhookUrl,
        ];
    }

    private function buildAdaptiveCard(ScheduledAction $action, string $token): array
    {
        $timeout = $action->gate['timeout'] ?? '24h';

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
                ],
                [
                    'type' => 'TextBlock',
                    'text' => $action->gate['message'] ?? $action->description,
                    'wrap' => true,
                ],
                [
                    'type' => 'TextBlock',
                    'text' => "Expires in {$timeout} | From: CallMeLater",
                    'size' => 'small',
                    'isSubtle' => true,
                ],
            ],
            'actions' => [
                [
                    'type' => 'Action.Http',
                    'title' => 'Confirm',
                    'method' => 'POST',
                    'url' => route('chat.webhook', ['provider' => 'teams']),
                    'body' => json_encode(['action' => 'confirm', 'token' => $token]),
                    'style' => 'positive',
                ],
                [
                    'type' => 'Action.Http',
                    'title' => 'Decline',
                    'method' => 'POST',
                    'url' => route('chat.webhook', ['provider' => 'teams']),
                    'body' => json_encode(['action' => 'decline', 'token' => $token]),
                    'style' => 'destructive',
                ],
                [
                    'type' => 'Action.Http',
                    'title' => 'Snooze 1h',
                    'method' => 'POST',
                    'url' => route('chat.webhook', ['provider' => 'teams']),
                    'body' => json_encode(['action' => 'snooze', 'token' => $token]),
                ],
            ],
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        // Teams uses JWT in Authorization header
        $token = $request->bearerToken();
        if (!$token) {
            return false;
        }

        // Verify JWT signature with Microsoft's public keys
        return $this->verifyTeamsJwt($token);
    }

    public function parseWebhookPayload(Request $request): array
    {
        $body = $request->json()->all();

        return [
            'response' => $body['action'],
            'token' => $body['token'],
            'user_id' => $body['from']['id'] ?? 'unknown',
        ];
    }

    public function updateCardWithResponse(...): void
    {
        // Teams: Replace card with static "responded" version
        // Implementation depends on whether using webhook or bot
    }
}
```

#### Controller: `app/Http/Controllers/ChatWebhookController.php`

```php
class ChatWebhookController extends Controller
{
    public function __construct(
        private ResponseProcessor $responseProcessor,
        private CreatorNotificationService $creatorNotificationService,
    ) {}

    public function webhook(Request $request, string $provider): Response
    {
        $integration = $this->getIntegration($provider);

        // Verify signature
        if (!$integration->verifyWebhookSignature($request)) {
            Log::warning('Chat webhook signature verification failed', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);
            abort(401);
        }

        // Parse payload
        $payload = $integration->parseWebhookPayload($request);

        // Process response via existing ResponseProcessor
        $result = $this->responseProcessor->processResponse(
            $payload['token'],
            $payload['response'],
            [
                'source' => $provider,
                'user_id' => $payload['user_id'],
            ]
        );

        return response('', 200);
    }

    private function getIntegration(string $provider): ChatIntegration
    {
        return match ($provider) {
            'teams' => app(TeamsIntegration::class),
            'slack' => app(SlackIntegration::class),
            default => abort(404),
        };
    }
}
```

#### Update: `app/Services/ReminderService.php`

```php
public function sendReminders(ScheduledAction $action): void
{
    $channels = $action->gate['channels'] ?? ['email'];

    foreach ($action->recipients as $recipient) {
        foreach ($channels as $channel) {
            match ($channel) {
                'email' => $this->sendEmailReminder($recipient, $action),
                'sms' => $this->sendSmsReminder($recipient, $action),
                'teams', 'slack' => $this->sendChatReminder($recipient, $action, $channel),
            };
        }
    }
}

private function sendChatReminder(
    ReminderRecipient $recipient,
    ScheduledAction $action,
    string $provider
): void {
    if (!$recipient->chat_destination) {
        Log::warning("Chat reminder requested but no {$provider} destination", [
            'action_id' => $action->id,
            'recipient_id' => $recipient->id,
        ]);
        return;
    }

    $integration = $this->getChatIntegration($provider);

    $result = $integration->sendDecisionCard(
        $action,
        $recipient,
        $recipient->response_token
    );

    $recipient->update([
        'chat_provider' => $provider,
        'chat_message_id' => $result['message_id'],
    ]);
}
```

### Routes

```php
// routes/web.php - Chat webhooks (signature-verified, no session)
Route::post('/webhooks/chat/{provider}', [ChatWebhookController::class, 'webhook'])
    ->name('chat.webhook')
    ->withoutMiddleware(['web', 'csrf']);

// routes/api.php - Connection management
Route::prefix('integrations')->group(function () {
    Route::get('/connections', [IntegrationController::class, 'index']);
    Route::post('/connections', [IntegrationController::class, 'store']);
    Route::delete('/connections/{id}', [IntegrationController::class, 'destroy']);
    Route::post('/connections/{id}/test', [IntegrationController::class, 'test']);
});
```

### Config Updates

#### `config/callmelater.php`

```php
'plans' => [
    'free' => [
        // ... existing ...
        'chat_integrations' => false,
    ],
    'pro' => [
        // ... existing ...
        'chat_integrations' => true,
    ],
    'business' => [
        // ... existing ...
        'chat_integrations' => true,
    ],
],
```

#### `config/services.php`

```php
'teams' => [
    'app_id' => env('TEAMS_APP_ID'),
    'app_secret' => env('TEAMS_APP_SECRET'),
    'tenant_id' => env('TEAMS_TENANT_ID'), // For single-tenant apps
],

'slack' => [
    'client_id' => env('SLACK_CLIENT_ID'),
    'client_secret' => env('SLACK_CLIENT_SECRET'),
    'signing_secret' => env('SLACK_SIGNING_SECRET'),
],
```

---

## Phase 2: Slack Integration

Once Teams is working, Slack becomes straightforward:

#### Service: `app/Services/Chat/SlackIntegration.php`

```php
class SlackIntegration implements ChatIntegration
{
    public function getChannel(): string
    {
        return 'slack';
    }

    public function sendDecisionCard(
        ScheduledAction $action,
        ReminderRecipient $recipient,
        string $responseToken
    ): array {
        $connection = $action->account->chatConnections()->slack()->active()->first();

        $response = Http::withToken($connection->slack_bot_token)
            ->post('https://slack.com/api/chat.postMessage', [
                'channel' => $recipient->chat_destination,
                'blocks' => $this->buildBlockKit($action, $responseToken),
            ]);

        $data = $response->json();

        return [
            'message_id' => $data['ts'],
            'channel_id' => $data['channel'],
        ];
    }

    private function buildBlockKit(ScheduledAction $action, string $token): array
    {
        return [
            [
                'type' => 'header',
                'text' => ['type' => 'plain_text', 'text' => "Action Required: {$action->name}"],
            ],
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => $action->gate['message'] ?? $action->description],
            ],
            [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Confirm'],
                        'style' => 'primary',
                        'action_id' => "confirm_{$token}",
                    ],
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Decline'],
                        'style' => 'danger',
                        'action_id' => "decline_{$token}",
                    ],
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Snooze 1h'],
                        'action_id' => "snooze_{$token}",
                    ],
                ],
            ],
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Slack-Signature');
        $timestamp = $request->header('X-Slack-Request-Timestamp');

        // Prevent replay attacks
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        $sigBasestring = "v0:{$timestamp}:{$request->getContent()}";
        $mySignature = 'v0=' . hash_hmac('sha256', $sigBasestring, config('services.slack.signing_secret'));

        return hash_equals($mySignature, $signature);
    }

    public function parseWebhookPayload(Request $request): array
    {
        $payload = json_decode($request->input('payload'), true);
        $action = $payload['actions'][0];

        [$response, $token] = explode('_', $action['action_id'], 2);

        return [
            'response' => $response,
            'token' => $token,
            'user_id' => $payload['user']['id'],
        ];
    }
}
```

---

## Creator Notifications

#### Service: `app/Services/CreatorNotificationService.php`

```php
class CreatorNotificationService
{
    public function notifyCreator(
        ScheduledAction $action,
        string $response,
        ?ReminderRecipient $respondent = null
    ): void {
        if (!$action->notify_creator_on_response) {
            return;
        }

        $creator = $action->createdByUser;
        if (!$creator) {
            return;
        }

        $respondentName = $respondent?->email ?? $respondent?->phone ?? 'Someone';

        Mail::to($creator->email)->send(new ResponseNotification(
            action: $action,
            response: $response,
            respondentName: $respondentName,
        ));
    }
}
```

#### Update: `app/Services/ResponseProcessor.php`

```php
public function processResponse(string $token, string $response, array $metadata = []): array
{
    // ... existing response processing ...

    // Notify creator if enabled (after successful processing)
    app(CreatorNotificationService::class)->notifyCreator(
        $action,
        $response,
        $recipient
    );

    return $result;
}
```

---

## UI Implementation

### Settings Page: Integrations Section

```
Settings > Integrations

┌─────────────────────────────────────────────────────────┐
│ Microsoft Teams                              [Pro]      │
├─────────────────────────────────────────────────────────┤
│ ○ No connections                                        │
│                                                         │
│ [+ Add Teams Webhook]                                   │
│                                                         │
│ How to get a webhook URL:                               │
│ 1. In Teams, go to the channel you want notifications  │
│ 2. Click ••• > Connectors > Incoming Webhook           │
│ 3. Name it "CallMeLater" and copy the URL              │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Slack                                        [Pro]      │
├─────────────────────────────────────────────────────────┤
│ ○ Not connected                                         │
│                                                         │
│ [Connect to Slack]                                      │
└─────────────────────────────────────────────────────────┘
```

### CreateAction.vue: Gate Configuration

```vue
<!-- Notification Channels -->
<div class="mb-3">
  <label class="form-label">Notification Channels</label>
  <div class="form-check">
    <input type="checkbox" v-model="channels" value="email" class="form-check-input">
    <label class="form-check-label">Email</label>
  </div>
  <div class="form-check">
    <input type="checkbox" v-model="channels" value="sms" class="form-check-input"
           :disabled="!canUseSms">
    <label class="form-check-label">SMS <span class="badge bg-secondary">Pro</span></label>
  </div>
  <div class="form-check">
    <input type="checkbox" v-model="channels" value="teams" class="form-check-input"
           :disabled="!hasTeamsConnection">
    <label class="form-check-label">
      Microsoft Teams
      <span v-if="!hasTeamsConnection" class="text-muted">(Connect in Settings)</span>
    </label>
  </div>
  <div class="form-check">
    <input type="checkbox" v-model="channels" value="slack" class="form-check-input"
           :disabled="!hasSlackConnection">
    <label class="form-check-label">
      Slack
      <span v-if="!hasSlackConnection" class="text-muted">(Connect in Settings)</span>
    </label>
  </div>
</div>

<!-- Creator Notification -->
<div class="form-check mb-3">
  <input type="checkbox" v-model="notifyCreatorOnResponse" class="form-check-input">
  <label class="form-check-label">Notify me when someone responds</label>
</div>

<!-- Teams/Slack Recipient (when those channels selected) -->
<div v-if="channels.includes('teams') || channels.includes('slack')" class="mb-3">
  <label class="form-label">Chat Recipient</label>
  <input type="text" v-model="recipient.chat_destination" class="form-control"
         placeholder="User ID, channel ID, or webhook URL">
  <div class="form-text">
    For Teams: paste your webhook URL. For Slack: use @user or #channel.
  </div>
</div>
```

---

## Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/..._create_chat_connections_table.php` | Unified connections table |
| `database/migrations/..._add_chat_fields_to_reminder_recipients.php` | Chat delivery tracking |
| `database/migrations/..._add_creator_notification_to_scheduled_actions.php` | Creator notification flag |
| `app/Contracts/ChatIntegration.php` | Interface for chat providers |
| `app/Models/ChatConnection.php` | Connection model |
| `app/Services/Chat/TeamsIntegration.php` | Teams implementation |
| `app/Services/Chat/SlackIntegration.php` | Slack implementation (Phase 2) |
| `app/Services/CreatorNotificationService.php` | Creator notifications |
| `app/Http/Controllers/ChatWebhookController.php` | Unified webhook handler |
| `app/Http/Controllers/Api/IntegrationController.php` | Connection management |
| `app/Mail/ResponseNotification.php` | Creator notification email |
| `tests/Feature/TeamsIntegrationTest.php` | Teams tests |
| `tests/Feature/SlackIntegrationTest.php` | Slack tests (Phase 2) |
| `tests/Feature/CreatorNotificationTest.php` | Notification tests |

## Files to Modify

| File | Changes |
|------|---------|
| `routes/web.php` | Add chat webhook route |
| `routes/api.php` | Add integration management routes |
| `config/services.php` | Add teams/slack credentials |
| `config/callmelater.php` | Add chat_integrations to plans |
| `app/Services/ReminderService.php` | Add chat channel handling |
| `app/Services/ResponseProcessor.php` | Add creator notification |
| `app/Http/Requests/Api/CreateActionRequest.php` | Add channels, notify_creator validation |
| `app/Models/Account.php` | Add chatConnections relationship |
| `app/Models/ReminderRecipient.php` | Add chat fields |
| `resources/js/pages/Settings.vue` | Add Integrations section |
| `resources/js/pages/CreateAction.vue` | Add channels selector |

---

## Implementation Timeline

### Phase 1: Teams + Creator Notifications (Week 1-2)

1. Database migrations
2. ChatIntegration interface + ChatConnection model
3. TeamsIntegration service
4. ChatWebhookController
5. ReminderService update for chat channels
6. CreatorNotificationService
7. UI: Settings integrations, CreateAction channels
8. Tests

### Phase 2: Slack (Week 3)

1. SlackIntegration service (implements same interface)
2. OAuth flow for Slack (optional, can start with bot token)
3. Slack-specific tests
4. Documentation

---

## Environment Variables

```env
# Microsoft Teams
TEAMS_APP_ID=your_app_id
TEAMS_APP_SECRET=your_app_secret

# Slack (Phase 2)
SLACK_CLIENT_ID=your_client_id
SLACK_CLIENT_SECRET=your_client_secret
SLACK_SIGNING_SECRET=your_signing_secret
```

---

## Verification

1. Run migrations: `php artisan migrate`
2. Run PHPStan: `./vendor/bin/phpstan analyse`
3. Run tests: `php artisan test --filter=TeamsIntegrationTest`
4. Run tests: `php artisan test --filter=CreatorNotificationTest`
5. Manual testing:
   - Add Teams webhook in Settings
   - Create gated action with Teams channel
   - Verify Adaptive Card appears in Teams
   - Click Confirm/Decline/Snooze
   - Verify response processes correctly
   - Verify creator receives notification email
6. Run full test suite: `php artisan test`
