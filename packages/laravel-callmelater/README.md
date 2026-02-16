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

### Chains (Multi-Step Workflows)

```php
use CallMeLater\Laravel\Facades\CallMeLater;

// Build a multi-step workflow
CallMeLater::chain('Process Order')
    ->input(['order_id' => 456])
    ->addHttpStep('Charge Payment')
        ->url('https://api.stripe.com/v1/charges')
        ->post()
        ->body(['amount' => 2999])
        ->maxAttempts(3)
        ->done()
    ->addGateStep('Approve Shipping')
        ->message('Approve shipment for order #456?')
        ->to('warehouse@example.com')
        ->timeout('2d')
        ->onTimeout('cancel')
        ->done()
    ->addDelayStep('Wait 1 hour')
        ->hours(1)
        ->done()
    ->addHttpStep('Ship Order')
        ->url('https://shipping.example.com/ship')
        ->post()
        ->body(['order_id' => '{{input.order_id}}'])
        ->condition("{{steps.1.response.action}} == confirmed")
        ->done()
    ->errorHandling('fail_chain')
    ->send();

// Get a chain
$chain = CallMeLater::getChain('chn_123');

// List chains with filters
$chains = CallMeLater::listChains(['status' => 'running']);

// Cancel a running chain
CallMeLater::cancelChain('chn_123');
```

### Templates (Reusable Action Definitions)

```php
use CallMeLater\Laravel\Facades\CallMeLater;

// Create a template
$tpl = CallMeLater::template('Invoice Reminder')
    ->description('Sends reminder to approve an invoice')
    ->mode('gated')
    ->gateConfig([
        'message' => 'Please approve invoice #{{invoice_id}}',
        'recipients' => ['email:{{approver_email}}'],
    ])
    ->placeholder('invoice_id', required: true, description: 'The invoice number')
    ->placeholder('approver_email', required: true)
    ->send();

// Trigger a template with placeholder values
CallMeLater::trigger($tpl['trigger_token'], [
    'invoice_id' => 'INV-001',
    'approver_email' => 'boss@example.com',
]);

// Update a template
CallMeLater::template('Updated Name')
    ->description('New description')
    ->update('tpl_123');

// CRUD operations
$template = CallMeLater::getTemplate('tpl_123');
$templates = CallMeLater::listTemplates();
CallMeLater::deleteTemplate('tpl_123');
CallMeLater::toggleTemplate('tpl_123');
CallMeLater::regenerateTemplateToken('tpl_123');
$limits = CallMeLater::templateLimits();
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

### Chain Builder

| Method | Description |
|--------|-------------|
| `input(array $data)` | Set chain input data (available as `{{input.*}}` in steps) |
| `addHttpStep(string $name)` | Add an HTTP step, returns `HttpStepBuilder` |
| `addGateStep(string $name)` | Add a gate (approval) step, returns `GateStepBuilder` |
| `addDelayStep(string $name)` | Add a delay step, returns `DelayStepBuilder` |
| `errorHandling(string $strategy)` | Set error handling (`fail_chain`, `skip_step`, `continue`) |
| `toArray()` | Get the API payload without sending |
| `dd()` | Dump the payload and die |
| `send()` | Send the chain |

### HTTP Step Builder (within chains)

| Method | Description |
|--------|-------------|
| `url(string $url)` | Set request URL |
| `method(string $method)` | Set HTTP method |
| `get()`, `post()`, `put()`, `patch()`, `delete()` | Method shortcuts |
| `headers(array $headers)` | Set request headers |
| `body(mixed $data)` | Set request body |
| `maxAttempts(int $max)` | Set retry attempts |
| `retryStrategy(string $strategy)` | Set retry strategy |
| `condition(string $expr)` | Set condition expression |
| `done()` / `add()` | Return to chain builder |

### Gate Step Builder (within chains)

| Method | Description |
|--------|-------------|
| `message(string $text)` | Set gate message |
| `to(string $email)` | Add email recipient |
| `toMany(array $emails)` | Add multiple recipients |
| `toRecipient(string $uri)` | Add raw recipient URI |
| `maxSnoozes(int $max)` | Set max snoozes |
| `requireAll()` / `firstResponse()` | Set confirmation mode |
| `timeout(string $duration)` | Set timeout (e.g., `'2d'`, `'24h'`) |
| `onTimeout(string $action)` | Set timeout action (`cancel`, `continue`, `fail`) |
| `condition(string $expr)` | Set condition expression |
| `done()` / `add()` | Return to chain builder |

### Delay Step Builder (within chains)

| Method | Description |
|--------|-------------|
| `duration(string $dur)` | Set raw duration (e.g., `'4h30m'`) |
| `minutes(int)`, `hours(int)`, `days(int)` | Duration shortcuts |
| `condition(string $expr)` | Set condition expression |
| `done()` / `add()` | Return to chain builder |

### Template Builder

| Method | Description |
|--------|-------------|
| `description(string $text)` | Set template description |
| `type(string $type)` | Set action type (`http`, `reminder`, `chain`) |
| `mode(string $mode)` | Set mode (`immediate`, `gated`) |
| `timezone(string $tz)` | Set default timezone |
| `requestConfig(array $config)` | Set HTTP request config |
| `gateConfig(array $config)` | Set gate config |
| `maxAttempts(int $max)` | Set retry attempts |
| `retryStrategy(string $strategy)` | Set retry strategy |
| `placeholder(string $key, ...)` | Add a placeholder (with `required`, `description`, `default`) |
| `placeholders(array $list)` | Set all placeholders at once |
| `chainSteps(array $steps)` | Set chain steps (for chain templates) |
| `chainErrorHandling(string $strategy)` | Set chain error handling |
| `coordinationKeys(array $keys)` | Set coordination keys |
| `coordinationConfig(array $config)` | Set coordination config |
| `toArray()` | Get the API payload without sending |
| `dd()` | Dump the payload and die |
| `send()` / `create()` | Create the template |
| `update(string $id)` | Update an existing template |

### Chain & Template Management

| Method | Description |
|--------|-------------|
| `CallMeLater::chain($name)` | Create a chain builder |
| `CallMeLater::getChain($id)` | Get a chain by ID |
| `CallMeLater::listChains($filters)` | List chains |
| `CallMeLater::cancelChain($id)` | Cancel a running chain |
| `CallMeLater::template($name)` | Create a template builder |
| `CallMeLater::getTemplate($id)` | Get a template by ID |
| `CallMeLater::listTemplates($filters)` | List templates |
| `CallMeLater::deleteTemplate($id)` | Delete a template |
| `CallMeLater::toggleTemplate($id)` | Toggle enabled/disabled |
| `CallMeLater::regenerateTemplateToken($id)` | Regenerate trigger token |
| `CallMeLater::templateLimits()` | Get account template limits |
| `CallMeLater::trigger($token, $params)` | Trigger a template |

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
