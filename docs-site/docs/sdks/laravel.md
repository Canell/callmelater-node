---
sidebar_position: 2
---

# Laravel SDK

Fluent Laravel SDK for CallMeLater with Facades, webhook events, and Artisan commands.

## Installation

```bash
composer require callmelater/laravel
```

Add to your `.env`:

```env
CALLMELATER_API_TOKEN=sk_live_your_token_here
CALLMELATER_WEBHOOK_SECRET=your_webhook_secret
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=callmelater-config
```

## HTTP Actions

```php
use CallMeLater\Laravel\Facades\CallMeLater;

// Simple
CallMeLater::http('https://api.example.com/process')
    ->post()
    ->payload(['user_id' => 123])
    ->inHours(2)
    ->send();

// With all options
CallMeLater::http('https://api.example.com/webhook')
    ->name('Process order #456')
    ->post()
    ->headers(['X-Custom' => 'value'])
    ->payload(['order_id' => 456])
    ->at(now()->addDays(3))
    ->timezone('America/New_York')
    ->retry(5, 'exponential', 120)
    ->callback('https://myapp.com/callbacks/callmelater')
    ->send();

// Using presets
CallMeLater::http('https://api.example.com/reminder')
    ->post()
    ->at('tomorrow')  // or 'next_monday', 'next_week', etc.
    ->send();
```

## Reminders

```php
// Simple
CallMeLater::reminder('Approve deployment')
    ->to('manager@example.com')
    ->message('Please approve the production deployment')
    ->at('tomorrow 9am')
    ->send();

// With all options
CallMeLater::reminder('Weekly report sign-off')
    ->to('cfo@example.com')
    ->toMany(['ceo@example.com', 'coo@example.com'])
    ->message('Please review the weekly financial report')
    ->buttons('Approve', 'Reject')
    ->allowSnooze(3)
    ->requireAll()
    ->expiresInDays(7)
    ->escalateTo(['backup@example.com'], afterHours: 48)
    ->attach('https://example.com/report.pdf', 'Weekly Report')
    ->callback('https://myapp.com/callbacks/response')
    ->inDays(1)
    ->send();
```

## Recurring Actions

```php
// Repeat every 2 hours, up to 10 times
CallMeLater::http('https://api.example.com/health-check')
    ->post()
    ->inMinutes(5)
    ->everyHours(2)
    ->maxOccurrences(10)
    ->send();

// Weekly report, repeats forever
CallMeLater::http('https://api.example.com/reports/weekly')
    ->post()
    ->at('next_monday')
    ->timezone('America/New_York')
    ->everyWeeks(1)
    ->repeatForever()
    ->send();

// Recurring reminder until end of quarter
CallMeLater::reminder('Weekly standup check-in')
    ->to('team@example.com')
    ->message('Please confirm attendance')
    ->at('next_monday')
    ->everyWeeks(1)
    ->until('2026-06-30T23:59:59Z')
    ->send();
```

Recurrence options: `repeat(freq, unit)`, `every(freq, unit)`, `everyMinutes()`, `everyHours()`, `everyDays()`, `everyWeeks()`, `everyMonths()`, `maxOccurrences()`, `until()`, `repeatForever()`. Minimum interval: 5 minutes.

## Chains

```php
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
```

## Templates

```php
// Create
$tpl = CallMeLater::template('Invoice Reminder')
    ->description('Sends reminder to approve an invoice')
    ->mode('approval')
    ->gateConfig([
        'message' => 'Please approve invoice #{{invoice_id}}',
        'recipients' => ['email:{{approver_email}}'],
    ])
    ->placeholder('invoice_id', required: true, description: 'The invoice number')
    ->placeholder('approver_email', required: true)
    ->send();

// Trigger (no auth needed)
CallMeLater::trigger($tpl['trigger_token'], [
    'invoice_id' => 'INV-001',
    'approver_email' => 'boss@example.com',
]);

// Management
CallMeLater::getTemplate('tpl_123');
CallMeLater::listTemplates();
CallMeLater::deleteTemplate('tpl_123');
CallMeLater::toggleTemplate('tpl_123');
CallMeLater::regenerateTemplateToken('tpl_123');
CallMeLater::templateLimits();
```

## Managing Actions

```php
$action = CallMeLater::get('action_id');
$actions = CallMeLater::list(['status' => 'scheduled', 'per_page' => 50]);
CallMeLater::cancel('action_id');

$chain = CallMeLater::getChain('chn_123');
$chains = CallMeLater::listChains(['status' => 'running']);
CallMeLater::cancelChain('chn_123');
```

## Webhooks

Verify signatures and dispatch Laravel events automatically:

```php
// routes/web.php
Route::post('/webhooks/callmelater', function (Illuminate\Http\Request $request) {
    CallMeLater::webhooks()->handle($request);
    return response()->json(['received' => true]);
});
```

Or use the middleware:

```php
use CallMeLater\Laravel\Http\Middleware\VerifyCallMeLaterSignature;

Route::post('/webhooks/callmelater', [WebhookController::class, 'handle'])
    ->middleware(VerifyCallMeLaterSignature::class);
```

### Listening to events

```php
// EventServiceProvider
protected $listen = [
    \CallMeLater\Laravel\Events\ReminderResponded::class => [
        \App\Listeners\HandleReminderResponse::class,
    ],
];
```

```php
// app/Listeners/HandleReminderResponse.php
use CallMeLater\Laravel\Events\ReminderResponded;

class HandleReminderResponse
{
    public function handle(ReminderResponded $event): void
    {
        if ($event->isConfirmed()) {
            logger()->info("Approved by {$event->responderEmail}");
        } elseif ($event->isDeclined()) {
            logger()->warning("Declined: {$event->actionName}");
        }
    }
}
```

Events: `ActionExecuted`, `ActionFailed`, `ActionExpired`, `ReminderResponded`.

### Skip verification (local dev)

```php
CallMeLater::webhooks()->skipVerification()->handle($request);
$payload = CallMeLater::webhooks()->withoutEvents()->handle($request);
```

## Artisan Commands

```bash
php artisan callmelater:list
php artisan callmelater:list --status=scheduled --type=webhook
php artisan callmelater:cancel action_id_here
```

## Dependency Injection

```php
use CallMeLater\Laravel\CallMeLater;

class OrderController extends Controller
{
    public function __construct(private CallMeLater $callMeLater) {}

    public function scheduleFollowUp(Order $order)
    {
        $this->callMeLater->reminder("Follow up on order #{$order->id}")
            ->to($order->customer_email)
            ->message("How was your experience?")
            ->inDays(7)
            ->send();
    }
}
```

## Debugging

```php
// Inspect payload without sending
$payload = CallMeLater::http('https://example.com')
    ->post()
    ->inHours(2)
    ->toArray();

// Dump and die
CallMeLater::reminder('Test')->to('user@example.com')->dd();
```

## Error Handling

```php
use CallMeLater\Laravel\Exceptions\ApiException;
use CallMeLater\Laravel\Exceptions\AuthenticationException;

try {
    CallMeLater::http('https://example.com')->send();
} catch (AuthenticationException $e) {
    // Invalid or expired API token
} catch (ApiException $e) {
    $e->getStatusCode();         // 422, 404, etc.
    $e->getValidationErrors();   // ['field' => ['message']]
    $e->getResponseBody();       // Raw response
}
```
