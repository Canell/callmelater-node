# CallMeLater Node.js SDK

Official Node.js/TypeScript SDK for [CallMeLater](https://callmelater.io) — schedule durable HTTP calls and interactive human reminders.

## Installation

```bash
npm install callmelater
```

**Requirements:** Node.js 18+ (uses native `fetch`)

## Quick Start

```ts
import { CallMeLater } from 'callmelater';

const client = new CallMeLater({
  apiToken: 'sk_live_...',
});

// Schedule an HTTP call in 2 hours
await client.http('https://api.example.com/process')
  .post()
  .payload({ user_id: 123 })
  .inHours(2)
  .send();

// Send a reminder
await client.reminder('Approve deployment')
  .to('manager@example.com')
  .message('Please approve the production deployment')
  .allowSnooze(3)
  .timeout('7d')
  .at('tomorrow')
  .send();
```

## Configuration

```ts
const client = new CallMeLater({
  apiToken: 'sk_live_...',              // Required
  apiUrl: 'https://callmelater.io',     // Optional (default)
  webhookSecret: 'whsec_...',           // Optional, for webhook verification
  timezone: 'America/New_York',         // Optional, default timezone for scheduling
  retry: {                              // Optional, default retry config for HTTP actions
    maxAttempts: 3,
    retryStrategy: 'exponential',       // 'exponential' | 'linear' | 'fixed'
  },
});
```

## HTTP Actions

Schedule deferred HTTP requests with retry policies:

```ts
const action = await client.http('https://api.example.com/webhook')
  .post()                                    // or .get(), .put(), .patch(), .delete()
  .name('Process Order #123')
  .description('Process and ship the order')
  .headers({ 'X-Api-Key': 'secret' })
  .payload({ order_id: 123 })
  .inMinutes(30)                             // or .inHours(2), .inDays(1)
  .retry(5, 'exponential')
  .callback('https://myapp.com/webhook')
  .send();

console.log(action.id); // 'act_...'
```

### Scheduling Options

```ts
// Relative delay
.delay(5, 'minutes')    // or 'hours', 'days', 'weeks'
.inMinutes(30)
.inHours(2)
.inDays(7)

// Presets
.at('tomorrow')
.at('next_monday')
.at('1h')               // also: '2h', '4h', '1d', '3d', '1w', '1M'

// Specific datetime (sent as execute_at)
.at('2025-06-15 14:30:00')
.at(new Date(2025, 5, 15, 14, 30))

// Time only (sent as intent.at)
.at('14:30')
.atTime('14:30', '2025-06-15')   // time + optional date

// Timezone
.timezone('America/New_York')
```

### HTTP Action Options

```ts
.name('Action name')
.description('Detailed description')
.idempotencyKey('unique-key')
.headers({ 'X-Api-Key': 'secret' })
.header('X-Single', 'value')
.payload({ ... })                  // or .body({ ... })
.retry(3, 'exponential')          // maxAttempts, strategy
.noRetry()                         // single attempt
.callback('https://...')           // or .onComplete('https://...')
.requestTimeout(30)                // seconds
.webhookSecret('secret')
.coordinationKeys(['user:123'])
.coordination({ on_create: 'replace_existing' })
```

## Reminders

Send interactive reminders with approve/snooze responses:

```ts
const reminder = await client.reminder('Approve deployment')
  .to('manager@example.com')
  .message('Please approve the production deployment')
  .allowSnooze(3)
  .timeout('7d')
  .onTimeout('cancel')
  .channels(['email', 'push'])
  .inHours(1)
  .callback('https://myapp.com/webhook')
  .send();
```

### Recipients

```ts
.to('user@example.com')                // Email
.toMany(['alice@co.com', 'bob@co.com']) // Multiple emails
.toPhone('+1234567890')                 // SMS
.toChannel('channel-uuid')             // Channel
.toRecipient('custom:uri')             // Raw URI
```

### Gate Options

```ts
.message('Please approve this')
.allowSnooze(3)                    // Max snoozes (default: 5)
.noSnooze()                        // Disable snoozing
.timeout('7d')                     // Gate timeout (e.g. '4h', '7d', '1w')
.onTimeout('cancel')               // 'cancel' | 'expire' | 'approve'
.channels(['email', 'sms', 'push', 'teams', 'slack'])
.integrationIds(['uuid-1'])        // Teams/Slack integration IDs
.requireAll()                      // All recipients must respond
.firstResponse()                   // Complete on first response
.escalateTo(['boss@co.com'], 24)   // Escalation after N hours
.attach('https://example.com/report.pdf', 'Report')
.notifyCreatorOnResponse()         // Notify creator when recipients respond
```

### Recurring Actions

Make any action repeat on a schedule:

```ts
// Repeat every 2 hours, up to 10 times
await client.http('https://api.example.com/reports/generate')
  .post()
  .payload({ type: 'health_check' })
  .inMinutes(5)
  .everyHours(2)
  .maxOccurrences(10)
  .send();

// Repeat every day forever
await client.http('https://api.example.com/cleanup')
  .post()
  .inHours(1)
  .everyDays(1)
  .repeatForever()
  .send();

// Repeat weekly until a specific date
await client.http('https://api.example.com/reports/weekly')
  .post()
  .at('next_monday')
  .everyWeeks(1)
  .until('2026-12-31')
  .send();
```

#### Recurrence Options

```ts
.repeat(2, 'hours')               // Every 2 hours
.every(1, 'days')                  // Alias for repeat()
.everyMinutes(30)                  // Every 30 minutes
.everyHours(2)                     // Every 2 hours
.everyDays(1)                      // Every day
.everyWeeks(1)                     // Every week
.everyMonths(1)                    // Every month
.maxOccurrences(10)                // Stop after 10 executions
.until('2026-12-31')               // Stop after a date
.repeatForever()                   // No end (default)
```

Recurring actions work with both HTTP actions and reminders. The minimum interval is 5 minutes.

## Chains

Build multi-step workflows with HTTP calls, gates, and delays:

```ts
const chain = await client.chain('Process Order')
  .input({ order_id: 42 })
  .errorHandling('fail_chain')
  .addHttpStep('Validate')
    .url('https://api.example.com/validate')
    .post()
    .body({ order_id: '{{input.order_id}}' })
    .done()
  .addGateStep('Manager Approval')
    .message('Approve order #{{input.order_id}}?')
    .to('manager@example.com')
    .timeout('24h')
    .onTimeout('cancel')
    .done()
  .addDelayStep('Cooling Period')
    .hours(1)
    .done()
  .addHttpStep('Execute')
    .url('https://api.example.com/execute')
    .post()
    .condition('{{steps.2.response.action}} == confirmed')
    .done()
  .send();
```

### Step Types

**HTTP Step:**
```ts
.addHttpStep('name')
  .url('https://...')
  .post()             // or .get(), .put(), .patch(), .delete()
  .headers({...})
  .body({...})
  .condition('...')
  .maxAttempts(3)
  .retryStrategy('exponential')
  .done()             // or .add() — returns to chain builder
```

**Gate Step:**
```ts
.addGateStep('name')
  .message('...')
  .to('email@...')
  .toMany([...])
  .maxSnoozes(3)
  .requireAll()       // or .firstResponse()
  .timeout('24h')
  .onTimeout('cancel') // 'cancel' | 'continue' | 'fail'
  .condition('...')
  .done()
```

**Delay Step:**
```ts
.addDelayStep('name')
  .minutes(30)        // or .hours(2), .days(1), .duration('45m')
  .condition('...')
  .done()
```

## Templates

Create reusable action templates with placeholders:

```ts
// Create a template
const template = await client.template('Process Order')
  .description('Template for order processing')
  .type('action')
  .mode('immediate')
  .requestConfig({
    url: 'https://api.example.com/process',
    method: 'POST',
    body: { order_id: '{{order_id}}', amount: '{{amount}}' },
  })
  .placeholder('order_id', true, 'The order ID')
  .placeholder('amount', false, 'Order amount', '0.00')
  .maxAttempts(3)
  .send();

// Trigger a template
const action = await client.trigger(template.trigger_token, {
  order_id: 'ORD-123',
  amount: '99.99',
  intent: { delay: '5m' },
});

// Update a template
await client.template('Updated Name')
  .description('New description')
  .update(template.id);
```

### Template Management

```ts
await client.getTemplate(id);
await client.listTemplates();
await client.deleteTemplate(id);
await client.toggleTemplate(id);            // Toggle active/inactive
await client.regenerateTemplateToken(id);   // New trigger token
await client.templateLimits();              // Account limits
```

## Actions & Chains CRUD

```ts
// Actions
await client.getAction(id);
await client.listActions({ status: 'resolved' });
await client.cancelAction(id);
await client.cancelActionByKey('idempotency-key');
await client.retryAction(id);
await client.sendActionNow(id);
await client.sendActionAgain(id);
await client.testAction({ url: 'https://example.com', method: 'GET' });

// Quota
await client.getQuota();
await client.getCoordinationKeys();

// Chains
await client.getChain(id);
await client.listChains({ limit: '10' });
await client.cancelChain(id);
```

## Webhooks

Handle incoming webhook events with signature verification:

```ts
import express from 'express';

const app = express();

app.post('/webhooks/callmelater', express.raw({ type: 'application/json' }), (req, res) => {
  const handler = client.webhooks();

  try {
    const event = handler.handle(
      req.body.toString(),
      req.headers['x-callmelater-signature'] as string,
    );

    console.log('Event:', event.event, event.action_id);
    res.sendStatus(200);
  } catch (err) {
    console.error('Webhook error:', err);
    res.sendStatus(400);
  }
});
```

### Event Emitter

```ts
const handler = client.webhooks();

handler.on('action.executed', (event) => {
  console.log('Action executed:', event.action_id);
});

handler.on('action.failed', (event) => {
  console.log('Action failed:', event.action_id);
});

handler.on('action.expired', (event) => {
  console.log('Action expired:', event.action_id);
});

handler.on('reminder.responded', (event) => {
  console.log('Reminder response:', event.payload.response);
});
```

### Manual Signature Verification

```ts
const handler = client.webhooks();

// Throws on invalid signature
handler.verifySignature(rawBody, signatureHeader);

// Returns boolean
handler.isValidSignature(rawBody, signatureHeader);

// Skip verification (not recommended for production)
handler.skipVerification().handle(body);
```

## Error Handling

```ts
import { ApiError, ConfigurationError, SignatureVerificationError } from 'callmelater';

try {
  await client.getAction('nonexistent');
} catch (err) {
  if (err instanceof ApiError) {
    console.log(err.statusCode);          // 404
    console.log(err.validationErrors);    // { field: ['message'] }
    console.log(err.responseBody);        // Raw response
  }
}
```

### Error Classes

- **`CallMeLaterError`** — Base error class
- **`ApiError`** — HTTP API errors (includes `statusCode`, `validationErrors`, `responseBody`)
- **`ConfigurationError`** — Missing API token or webhook secret
- **`SignatureVerificationError`** — Invalid webhook signature

## Inspect Payloads

Use `.toJSON()` to inspect the payload without sending:

```ts
const payload = client.http('https://example.com')
  .post()
  .payload({ test: true })
  .inMinutes(5)
  .toJSON();

console.log(JSON.stringify(payload, null, 2));
```

## TypeScript

The SDK is written in TypeScript and ships with full type declarations. All builder methods return `this` for fluent chaining.

## License

MIT
