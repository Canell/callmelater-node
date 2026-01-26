---
sidebar_position: 4
---

# Error Handling

Handle failures gracefully in your webhook endpoints.

## Webhook Failures

When your webhook endpoint fails, CallMeLater retries based on your configuration.

### What Triggers a Retry

| Response | Behavior |
|----------|----------|
| 2xx | Success — no retry |
| 4xx (except 429) | Permanent failure — no retry |
| 429 | Rate limited — retry with backoff |
| 5xx | Server error — retry |
| Timeout | Connection issue — retry |
| Network error | Connection issue — retry |

### Retry Timeline (Exponential)

| Attempt | Delay After |
|---------|-------------|
| 1 | Immediate |
| 2 | 1 minute |
| 3 | 5 minutes |
| 4 | 15 minutes |
| 5 | 1 hour |

## Making Webhooks Resilient

### 1. Return 200 Quickly

Process asynchronously to avoid timeouts:

```javascript
app.post('/webhook', async (req, res) => {
  // Acknowledge immediately
  res.status(200).send('OK');

  // Process in background
  try {
    await processWebhook(req.body);
  } catch (error) {
    console.error('Background processing failed:', error);
    // Handle error (alert, retry queue, etc.)
  }
});
```

### 2. Handle Duplicate Deliveries

Your webhook might receive the same payload twice. Use the action ID to deduplicate:

```javascript
const actionId = req.headers['x-callmelater-action-id'];

// Check if already processed
const processed = await redis.get(`processed:${actionId}`);
if (processed) {
  return res.status(200).send('Already processed');
}

// Process the webhook
await processWebhook(req.body);

// Mark as processed
await redis.setex(`processed:${actionId}`, 86400, '1'); // 24h TTL
```

### 3. Use Transactions

Make your processing atomic:

```javascript
await db.transaction(async (trx) => {
  // All operations succeed or all fail
  await updateUser(trx, userId);
  await createLog(trx, actionId);
  await sendNotification(trx, userId);
});
```

### 4. Return Appropriate Status Codes

```javascript
app.post('/webhook', async (req, res) => {
  try {
    // Validate payload
    if (!isValidPayload(req.body)) {
      return res.status(400).send('Invalid payload'); // No retry
    }

    // Check dependencies
    if (!await isDatabaseAvailable()) {
      return res.status(503).send('Service unavailable'); // Retry
    }

    await processWebhook(req.body);
    res.status(200).send('OK');
  } catch (error) {
    console.error(error);
    res.status(500).send('Internal error'); // Retry
  }
});
```

## Monitoring Failures

### Check Action Status

```javascript
const action = await callmelater.getAction(actionId);

if (action.status === 'failed') {
  console.log('Failed after', action.attempt_count, 'attempts');
  console.log('Last error:', action.delivery_attempts.at(-1).error_message);
}
```

### List Failed Actions

```bash
GET /api/v1/actions?status=failed
```

### Retry a Failed Action

You can't retry a failed action directly. Instead:
1. Fix the underlying issue
2. Create a new action with a new idempotency key

## Alerting

Set up alerts for failed actions:

```javascript
// Daily check for failed actions
const failed = await callmelater.listActions({ status: 'failed' });

if (failed.data.length > 0) {
  await sendSlackAlert(`${failed.data.length} actions failed`);
}
```
