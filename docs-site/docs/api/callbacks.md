---
sidebar_position: 7
---

# Action Callbacks

Receive webhook notifications when your actions are executed, fail, or expire. Callbacks let you track action lifecycle events without polling the API.

## Overview

When you create an action with a `callback_url`, CallMeLater sends HTTP POST requests to notify you of these events:

| Event | Description |
|-------|-------------|
| `action.executed` | The HTTP action was successfully delivered (2xx response) |
| `action.failed` | The action failed permanently (4xx response or max retries exhausted) |
| `action.expired` | A gated (reminder) action expired without response |

## Enabling Callbacks

Add a `callback_url` when creating an action:

```bash
curl -X POST https://callmelater.io/api/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Sync inventory",
    "type": "http",
    "schedule": {"preset": "in_1_hour"},
    "request": {
      "method": "POST",
      "url": "https://api.example.com/sync"
    },
    "callback_url": "https://your-app.com/webhooks/callmelater"
  }'
```

## Payload Format

### Common Fields

All callback payloads include:

```json
{
  "event": "action.executed",
  "action_id": "abc123-def456",
  "action_name": "Sync inventory",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### action.executed

Sent when an HTTP action receives a successful (2xx) response:

```json
{
  "event": "action.executed",
  "action_id": "abc123-def456",
  "action_name": "Sync inventory",
  "timestamp": "2024-01-15T10:30:00Z",
  "execution": {
    "status_code": 200,
    "duration_ms": 150,
    "attempt_number": 1
  }
}
```

### action.failed

Sent when an action fails permanently:

```json
{
  "event": "action.failed",
  "action_id": "abc123-def456",
  "action_name": "Sync inventory",
  "timestamp": "2024-01-15T10:30:00Z",
  "failure": {
    "reason": "HTTP 500: Failed after 3 attempts",
    "status_code": 500,
    "total_attempts": 3,
    "error_message": null
  }
}
```

For network errors:

```json
{
  "event": "action.failed",
  "action_id": "abc123-def456",
  "action_name": "Sync inventory",
  "timestamp": "2024-01-15T10:30:00Z",
  "failure": {
    "reason": "System error: Connection timed out (after 3 attempts)",
    "status_code": null,
    "total_attempts": 3,
    "error_message": "Connection timed out after 30 seconds"
  }
}
```

### action.expired

Sent when a gated (reminder) action expires without receiving a response:

```json
{
  "event": "action.expired",
  "action_id": "abc123-def456",
  "action_name": "Deploy approval",
  "timestamp": "2024-01-15T10:30:00Z",
  "expiration": {
    "expired_at": "2024-01-15T10:30:00Z"
  }
}
```

## Headers

Callback requests include these headers:

| Header | Description |
|--------|-------------|
| `Content-Type` | `application/json` |
| `X-CallMeLater-Action-Id` | Action UUID |
| `X-CallMeLater-Timestamp` | Unix timestamp |
| `X-CallMeLater-Event` | Event type (e.g., `action.executed`) |
| `X-CallMeLater-Signature` | HMAC signature (if webhook secret configured) |

## Signature Verification

Callbacks are signed using your account's webhook secret or the action's custom `webhook_secret`. Verify signatures the same way as [regular webhooks](/api/webhooks#verifying-signatures).

## Retry Behavior

If your callback endpoint fails to respond, CallMeLater retries with exponential backoff:

| Attempt | Delay |
|---------|-------|
| 1 | Immediate |
| 2 | 1 minute |
| 3 | 5 minutes |

After 3 failed attempts, the callback is abandoned (but this does not affect the action's status).

**Retryable failures:**
- 5xx server errors
- Network timeouts
- Connection errors

**Non-retryable failures:**
- 4xx client errors (except 429)
- Invalid URL/hostname

## Best Practices

### 1. Respond with 2xx quickly

Return a success status immediately:

```javascript
app.post('/webhooks/callmelater', (req, res) => {
  res.status(200).send('OK');

  // Process asynchronously
  processCallback(req.body);
});
```

### 2. Handle all event types

Your handler should gracefully handle any event:

```javascript
app.post('/webhooks/callmelater', (req, res) => {
  const { event, action_id } = req.body;

  switch (event) {
    case 'action.executed':
      handleSuccess(req.body);
      break;
    case 'action.failed':
      handleFailure(req.body);
      break;
    case 'action.expired':
      handleExpiration(req.body);
      break;
    default:
      console.log(`Unknown event: ${event}`);
  }

  res.status(200).send('OK');
});
```

### 3. Make handlers idempotent

Callbacks may be delivered more than once. Use the action ID and event type to deduplicate:

```javascript
const key = `${req.body.action_id}:${req.body.event}`;

if (await alreadyProcessed(key)) {
  return res.status(200).send('Already processed');
}

await processCallback(req.body);
await markAsProcessed(key);
```

### 4. Verify signatures in production

Always verify the signature to ensure callbacks are from CallMeLater.

## Use Cases

### Workflow automation

Trigger downstream actions when an HTTP call completes:

```javascript
if (event === 'action.executed') {
  await updateDashboard(action_id);
  await notifySlack(`Action ${action_name} completed successfully`);
}
```

### Alerting on failures

Get notified when scheduled tasks fail:

```javascript
if (event === 'action.failed') {
  await pagerDuty.createIncident({
    title: `CallMeLater action failed: ${action_name}`,
    body: failure.reason
  });
}
```

### Reminder escalation

Handle expired approvals:

```javascript
if (event === 'action.expired') {
  await escalateToManager(action_id);
  await sendSlackReminder(`Approval expired: ${action_name}`);
}
```

## Integration with n8n

The [CallMeLater n8n node](/guides/n8n-integration) includes a trigger that listens for these callback events. Configure the trigger to receive real-time notifications when your actions complete.
