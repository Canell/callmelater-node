# CallMeLater Laravel SDK

A fluent Laravel SDK for [CallMeLater](https://callmelater.io) - schedule durable HTTP calls and interactive reminders.

## Installation

```bash
composer require callmelater/laravel
```

## Configuration

Add your API credentials to `.env`:

```env
CALLMELATER_API_TOKEN=sk_live_your_token_here
CALLMELATER_WEBHOOK_SECRET=your_webhook_secret
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=callmelater-config
```

## Usage

### Scheduling HTTP Calls

```php
use CallMeLater\Laravel\Facades\CallMeLater;

// Simple scheduled request
CallMeLater::http('https://api.example.com/process')
    ->post()
    ->payload(['user_id' => 123])
    ->inHours(2)
    ->send();

// With full options
CallMeLater::http('https://api.example.com/webhook')
    ->name('Process order #456')
    ->method('POST')
    ->headers(['X-Custom-Header' => 'value'])
    ->payload(['order_id' => 456, 'action' => 'ship'])
    ->at(now()->addDays(3))
    ->timezone('America/New_York')
    ->retry(5, 'exponential', 120)
    ->callback('https://myapp.com/callbacks/callmelater')
    ->metadata(['source' => 'laravel-app'])
    ->send();

// Using presets
CallMeLater::http('https://api.example.com/reminder')
    ->post()
    ->at('tomorrow')  // or 'next_monday', 'end_of_week', etc.
    ->send();
```

### Sending Reminders

```php
use CallMeLater\Laravel\Facades\CallMeLater;

// Simple reminder
CallMeLater::reminder('Approve deployment')
    ->to('manager@example.com')
    ->message('Please approve the production deployment for v2.0')
    ->at('tomorrow 9am')
    ->send();

// With all options
CallMeLater::reminder('Weekly report sign-off')
    ->to('cfo@example.com')
    ->toMany(['ceo@example.com', 'coo@example.com'])
    ->message('Please review and approve the weekly financial report')
    ->buttons('Approve', 'Reject')
    ->allowSnooze(3)
    ->requireAll()  // All recipients must respond
    ->expiresInDays(7)
    ->escalateTo(['backup-approver@example.com'], afterHours: 48)
    ->attach('https://example.com/reports/weekly.pdf', 'Weekly Report.pdf')
    ->callback('https://myapp.com/callbacks/reminder-response')
    ->metadata(['report_id' => 'WR-2024-01'])
    ->inDays(1)
    ->send();
```

### Managing Actions

```php
// Get an action
$action = CallMeLater::get('action_id_here');

// List actions with filters
$actions = CallMeLater::list([
    'status' => 'resolved',
    'type' => 'webhook',       // 'webhook' for HTTP calls, 'approval' for reminders
    'per_page' => 50,
]);

// Cancel an action
CallMeLater::cancel('action_id_here');
```

### Handling Webhooks

The SDK provides a built-in webhook handler that verifies signatures and dispatches Laravel events automatically:

```php
// routes/web.php
use CallMeLater\Laravel\Facades\CallMeLater;

Route::post('/webhooks/callmelater', function (Illuminate\Http\Request $request) {
    CallMeLater::webhooks()->handle($request);
    return response()->json(['received' => true]);
});
```

This verifies the `X-CallMeLater-Signature` header and dispatches the appropriate Laravel event (`ActionExecuted`, `ActionFailed`, `ActionExpired`, or `ReminderResponded`).

You can also skip signature verification (useful in local development) or disable event dispatching:

```php
// Skip signature verification
CallMeLater::webhooks()->skipVerification()->handle($request);

// Handle without dispatching events (returns the parsed payload)
$payload = CallMeLater::webhooks()->withoutEvents()->handle($request);
```

Alternatively, use the middleware for route-level signature verification:

```php
use CallMeLater\Laravel\Http\Middleware\VerifyCallMeLaterSignature;

Route::post('/webhooks/callmelater', [WebhookController::class, 'handle'])
    ->middleware(VerifyCallMeLaterSignature::class);
```

### Listening to Events

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \CallMeLater\Laravel\Events\ReminderResponded::class => [
        \App\Listeners\HandleReminderResponse::class,
    ],
];
```

```php
// app/Listeners/HandleReminderResponse.php
namespace App\Listeners;

use CallMeLater\Laravel\Events\ReminderResponded;

class HandleReminderResponse
{
    public function handle(ReminderResponded $event): void
    {
        if ($event->isConfirmed()) {
            // Handle confirmation
            logger()->info("Reminder {$event->actionName} was confirmed by {$event->responderEmail}");
        } elseif ($event->isDeclined()) {
            // Handle decline
            logger()->warning("Reminder {$event->actionName} was declined");
        }
    }
}
```

## Artisan Commands

```bash
# List your scheduled actions
php artisan callmelater:list
php artisan callmelater:list --status=resolved --type=webhook

# Cancel an action
php artisan callmelater:cancel action_id_here
```

## Dependency Injection

You can also use dependency injection instead of the facade:

```php
use CallMeLater\Laravel\CallMeLater;

class OrderController extends Controller
{
    public function __construct(
        private CallMeLater $callMeLater
    ) {}

    public function scheduleFollowUp(Order $order)
    {
        $this->callMeLater->reminder("Follow up on order #{$order->id}")
            ->to($order->customer_email)
            ->message("How was your experience with your recent order?")
            ->inDays(7)
            ->send();
    }
}
```

## Debugging

Both builders provide methods to inspect the API payload before sending:

```php
// Inspect the payload as an array
$payload = CallMeLater::http('https://api.example.com/process')
    ->post()
    ->payload(['user_id' => 123])
    ->inHours(2)
    ->toArray();

// Dump and die (useful during development)
CallMeLater::reminder('Test')
    ->to('user@example.com')
    ->message('Debug this')
    ->inHours(1)
    ->dd();
```

## Error Handling

The SDK throws specific exceptions for different error types:

```
CallMeLaterException (base)
├── ConfigurationException     — Missing API token or webhook secret
└── ApiException               — API returned an error response
    └── AuthenticationException — 401 Unauthorized

SignatureVerificationException — Invalid webhook signature (extends InvalidArgumentException)
```

### Catching API Errors

```php
use CallMeLater\Laravel\Exceptions\ApiException;
use CallMeLater\Laravel\Exceptions\AuthenticationException;

try {
    CallMeLater::http('https://example.com')->send();
} catch (AuthenticationException $e) {
    // Invalid or expired API token
    logger()->error('Auth failed: ' . $e->getMessage());
} catch (ApiException $e) {
    // Access the HTTP status code
    $e->getStatusCode();       // 422, 404, 500, etc.

    // Access the full validation error bag (for 422 responses)
    $e->getValidationErrors(); // ['mode' => ['The selected mode is invalid.'], ...]
    $e->getErrorBag();         // Alias for getValidationErrors()

    // Access the raw response body
    $e->getResponseBody();
}
```

## API Reference

### HTTP Action Builder

| Method | Description |
|--------|-------------|
| `method(string $method)` | Set HTTP method (GET, POST, PUT, PATCH, DELETE) |
| `get()`, `post()`, `put()`, `patch()`, `delete()` | Shortcut methods |
| `headers(array $headers)` | Set request headers |
| `header(string $key, string $value)` | Add a single header |
| `payload(mixed $data)` | Set request body |
| `name(string $name)` | Set a friendly name |
| `at(DateTime\|string $time)` | Schedule at specific time or preset |
| `delay(int $amount, string $unit)` | Schedule after delay |
| `inMinutes(int)`, `inHours(int)`, `inDays(int)` | Delay shortcuts |
| `timezone(string $tz)` | Set timezone |
| `retry(int $max, string $backoff, int $delay)` | Configure retry policy |
| `noRetry()` | Disable retries |
| `callback(string $url)` | Set callback URL |
| `metadata(array $data)` | Set metadata |
| `meta(string $key, mixed $value)` | Add a single metadata entry |
| `idempotencyKey(string $key)` | Set idempotency key |
| `toArray()` | Get the API payload without sending |
| `dd()` | Dump the payload and die |
| `send()` | Send the action |

### Reminder Builder

| Method | Description |
|--------|-------------|
| `to(string $email)` | Add email recipient |
| `toMany(array $emails)` | Add multiple recipients |
| `toPhone(string $phone)` | Add SMS recipient |
| `toChannel(string $uuid)` | Add chat channel recipient |
| `message(string $text)` | Set reminder message |
| `buttons(string $confirm, string $decline)` | Customize button text |
| `allowSnooze(int $max)` | Allow snoozing |
| `noSnooze()` | Disable snoozing |
| `expiresInDays(int $days)` | Set token expiry |
| `requireAll()` | Require all recipients to respond |
| `firstResponse()` | Complete on first response |
| `escalateTo(array $contacts, int $hours)` | Add escalation |
| `attach(string $url, ?string $name)` | Add attachment |
| `callback(string $url)` | Set callback URL |
| `metadata(array $data)` | Add metadata |
| `toRecipient(string $selector)` | Add a raw recipient selector URI |
| `toArray()` | Get the API payload without sending |
| `dd()` | Dump the payload and die |
| `send()` | Send the reminder |

### Signature Verification

| Method | Description |
|--------|-------------|
| `CallMeLater::verifySignature($request)` | Verify signature or throw `SignatureVerificationException` |
| `CallMeLater::isValidSignature($request)` | Returns `true`/`false` without throwing |

## Testing

Run the SDK test suite:

```bash
cd packages/laravel-callmelater
composer install
./vendor/bin/phpunit
```

## License

MIT
