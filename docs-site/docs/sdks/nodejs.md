---
sidebar_position: 1
---

# Node.js SDK

Official TypeScript SDK for CallMeLater. Zero dependencies, ESM + CJS.

## Installation

```bash
npm install callmelater
```

Requires Node.js 18+ (uses native `fetch`).

## Configuration

```ts
import { CallMeLater } from 'callmelater';

const client = new CallMeLater({
  apiToken: 'sk_live_...',              // Required
  apiUrl: 'https://callmelater.io',     // Optional (default)
  webhookSecret: 'whsec_...',           // For webhook signature verification
  timezone: 'America/New_York',         // Default timezone
  retry: {                              // Default retry config
    maxAttempts: 3,
    backoff: 'exponential',
    initialDelay: 60,
  },
});
```

## HTTP Actions

```ts
const action = await client.http('https://api.example.com/webhook')
  .post()
  .name('Process Order #123')
  .headers({ 'X-Api-Key': 'secret' })
  .payload({ order_id: 123 })
  .inMinutes(30)
  .retry(5, 'exponential', 120)
  .callback('https://myapp.com/webhook')
  .send();

console.log(action.id); // 'act_...'
```

### Scheduling options

```ts
// Relative delay
.inMinutes(30)
.inHours(2)
.inDays(7)
.delay(5, 'minutes')    // or 'hours', 'days', 'weeks'

// Presets
.at('tomorrow')
.at('next_monday')
.at('next_week')

// Specific datetime
.at('2025-06-15 14:30:00')
.at(new Date(2025, 5, 15, 14, 30))

// Timezone
.timezone('America/New_York')
```

## Reminders

```ts
await client.reminder('Approve deployment')
  .to('manager@example.com')
  .message('Please approve the production deployment')
  .buttons('Approve', 'Reject')
  .allowSnooze(3)
  .expiresInDays(7)
  .inHours(1)
  .callback('https://myapp.com/webhook')
  .send();
```

### Recipients

```ts
.to('user@example.com')                // Email
.toMany(['alice@co.com', 'bob@co.com']) // Multiple emails
.toPhone('+1234567890')                 // SMS
.toChannel('channel-uuid')             // Teams/Slack channel
```

### Options

```ts
.buttons('Yes', 'No')             // Button labels
.allowSnooze(3)                    // Max snoozes (0 to disable)
.noSnooze()                        // Disable snoozing
.requireAll()                      // All recipients must respond
.firstResponse()                   // Complete on first response
.expiresInDays(14)                 // Token expiry
.escalateTo(['boss@co.com'], 24)   // Escalate after N hours
.attach('https://example.com/report.pdf', 'Report')
```

## Chains

Multi-step workflows with HTTP calls, approvals, and delays:

```ts
await client.chain('Process Order')
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

## Templates

Reusable action configs with trigger URLs (no API key needed to trigger):

```ts
// Create
const template = await client.template('Process Order')
  .description('Template for order processing')
  .type('action')
  .mode('webhook')
  .requestConfig({
    url: 'https://api.example.com/process',
    method: 'POST',
    body: { order_id: '{{order_id}}' },
  })
  .placeholder('order_id', true, 'The order ID')
  .send();

// Trigger (public, no auth)
await client.trigger(template.trigger_token, {
  order_id: 'ORD-123',
});
```

### Management

```ts
await client.getTemplate(id);
await client.listTemplates();
await client.deleteTemplate(id);
await client.toggleTemplate(id);            // Enable/disable
await client.regenerateTemplateToken(id);
await client.templateLimits();
```

## Actions & Chains CRUD

```ts
await client.getAction(id);
await client.listActions({ status: 'scheduled' });
await client.cancelAction(id);

await client.getChain(id);
await client.listChains({ limit: '10' });
await client.cancelChain(id);
```

## Webhooks

Handle incoming events with signature verification:

```ts
import express from 'express';

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

### Event emitter

```ts
const handler = client.webhooks();

handler.on('action.executed', (e) => console.log('Executed:', e.action_id));
handler.on('action.failed', (e) => console.log('Failed:', e.action_id));
handler.on('reminder.responded', (e) => console.log('Response:', e.payload.response));
```

## Error Handling

```ts
import { ApiError, ConfigurationError, SignatureVerificationError } from 'callmelater';

try {
  await client.getAction('nonexistent');
} catch (err) {
  if (err instanceof ApiError) {
    console.log(err.statusCode);        // 404
    console.log(err.validationErrors);  // { field: ['message'] }
    console.log(err.responseBody);
  }
}
```

## Debugging

Inspect payloads without sending:

```ts
const payload = client.http('https://example.com')
  .post()
  .payload({ test: true })
  .inMinutes(5)
  .toJSON();

console.log(JSON.stringify(payload, null, 2));
```
