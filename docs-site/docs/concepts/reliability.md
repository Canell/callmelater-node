---
sidebar_position: 3
---

# Retries, Errors & Callbacks

CallMeLater automatically retries failed webhook deliveries and notifies you of outcomes via callbacks. This page covers how retries work, how to configure them, and how to receive lifecycle notifications.

## What triggers retries

| Response | Behavior |
|----------|----------|
| 2xx | Success -- no retry |
| 4xx (except 429) | Permanent failure -- no retry |
| 429 | Retry (rate limited) |
| 5xx | Retry (server error) |
| Timeout (30s) | Retry |
| Connection error | Retry |

A `4xx` response (other than 429) is treated as a permanent failure because it typically indicates a problem with the request itself, not a transient issue. Fix the request rather than retrying.

## Retry strategies

### Exponential backoff (default)

Delays increase between each attempt, giving transient issues time to resolve:

| Attempt | Wait before attempt | Cumulative |
|---------|---------------------|------------|
| 1 | Immediate | 0 |
| 2 | 1 minute | 1 min |
| 3 | 5 minutes | 6 min |
| 4 | 15 minutes | 21 min |
| 5 | 1 hour | 1h 21m |

Total window: approximately 1 hour 21 minutes from first attempt to last.

If `max_attempts` is increased beyond 5, attempt 6 would wait 4 hours.

```json
{
  "retry_strategy": "exponential",
  "max_attempts": 5
}
```

### Linear

Delays increase linearly using the formula `300 × attempt_count` seconds. Each subsequent retry waits longer than the previous one: 5 minutes, 10 minutes, 15 minutes, 20 minutes, and so on.

```json
{
  "retry_strategy": "linear",
  "max_attempts": 5
}
```

## Configuration

### `max_attempts`

Controls the maximum number of delivery attempts (including the first). Default: `5`.

```json
{
  "max_attempts": 10
}
```

The maximum value depends on your plan.

When all attempts are exhausted without a successful delivery, the action moves to `failed` status.

## Callbacks

Callbacks let you track action lifecycle events without polling. When an action completes, fails, or expires, CallMeLater sends an HTTP POST to your `callback_url`.

### Enabling callbacks

Set `callback_url` when creating an action:

```json
{
  "schedule": { "wait": "2h" },
  "request": { "url": "https://api.example.com/process" },
  "callback_url": "https://your-app.com/webhooks/callmelater"
}
```

### Events

| Event | When it fires |
|-------|---------------|
| `action.executed` | Webhook delivered successfully (2xx response received) |
| `action.failed` | All retry attempts exhausted or permanent failure |
| `action.expired` | Approval timed out without a response |
| `reminder.responded` | Someone responded to an approval (confirm, decline, or snooze) |

### Payload

```json
{
  "event": "action.executed",
  "action_id": "abc123-def456",
  "action_name": "Sync inventory",
  "timestamp": "2026-01-15T10:30:00Z",
  "payload": {
    "status": "executed",
    "response_code": 200,
    "duration_ms": 150,
    "attempt_number": 1
  }
}
```

For `action.failed`:

```json
{
  "event": "action.failed",
  "action_id": "abc123-def456",
  "action_name": "Sync inventory",
  "timestamp": "2026-01-15T10:30:00Z",
  "payload": {
    "status": "failed",
    "response_code": 500,
    "total_attempts": 6,
    "error_message": "Service Unavailable"
  }
}
```

For `reminder.responded`:

```json
{
  "event": "reminder.responded",
  "action_id": "abc123-def456",
  "action_name": "Deploy approval",
  "timestamp": "2026-01-15T10:30:00Z",
  "payload": {
    "status": "executed",
    "response": "confirm",
    "respondent": "ops@example.com"
  }
}
```

### Callback retries

If your callback endpoint fails to respond, CallMeLater retries up to **3 attempts** with exponential backoff (immediate, 1 minute, 5 minutes). After 3 failed attempts, the callback is abandoned. This does not affect the action's status.

## Making webhooks resilient

Follow these practices to build reliable webhook handlers:

- **Return 200 quickly, process async.** Acknowledge receipt immediately and handle business logic in the background. Requests time out after 30 seconds.
- **Handle duplicates.** The same webhook may be delivered more than once. Use the action ID as a dedup key in your handler to avoid processing the same event twice.
- **Use appropriate status codes.** Return `200` for success, `500` if you want CallMeLater to retry, and `400` if the request is invalid and should not be retried.
- **Verify webhook signatures.** Validate the `X-CallMeLater-Signature` header to confirm requests originate from CallMeLater. See [Security](/reference/security) for implementation examples.
