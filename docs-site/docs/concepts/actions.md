---
sidebar_position: 1
---

# Actions

Everything in CallMeLater is an **action** — something scheduled to happen in the future.

## Types of Actions

### HTTP Actions

An HTTP action triggers a webhook at a scheduled time. The `type` defaults to `http`, so you can omit it.

```json
{
  "intent": {
    "preset": "tomorrow"
  },
  "http_request": {
    "method": "POST",
    "url": "https://api.example.com/webhook",
    "headers": {
      "X-Custom-Header": "value"
    },
    "body": {
      "event": "trial_expired",
      "user_id": 42
    }
  }
}
```

Use HTTP actions for:
- Trial expirations
- Delayed notifications
- Scheduled API calls
- Cleanup tasks
- Follow-up triggers

### Reminder Actions

A reminder action sends a message to one or more recipients, asking them to respond.

```json
{
  "type": "reminder",
  "intent": {
    "delay": "2h"
  },
  "message": "Please confirm the deployment",
  "escalation_rules": {
    "recipients": ["ops@example.com", "+1234567890"],
    "channels": ["email", "sms"]
  },
  "confirmation_mode": "first_response"
}
```

Use reminder actions for:
- Approval workflows
- Human confirmations
- Check-ins
- Escalation chains

## Scheduling

Every action needs an **intent** that defines when it should execute.

### Presets

Named time references:

| Preset | When |
|--------|------|
| `tomorrow` | Tomorrow at 9:00 AM |
| `next_week` | Next Monday at 9:00 AM |
| `next_monday` ... `next_sunday` | Next specific weekday at 9:00 AM |
| `1h`, `2h`, `4h` | In 1, 2, or 4 hours |
| `1d`, `3d`, `7d` | In 1, 3, or 7 days |
| `1w` | In 1 week |

```json
{
  "intent": {
    "preset": "next_monday",
    "timezone": "America/New_York"
  }
}
```

### Relative Delay

A duration from now:

```json
{
  "intent": {
    "delay": "30m"
  }
}
```

Supported units: `m` (minutes), `h` (hours), `d` (days), `w` (weeks)

### Exact Time

A specific UTC timestamp:

```json
{
  "intent": {
    "execute_at": "2025-04-01T14:30:00Z"
  }
}
```

## Idempotency

Use `idempotency_key` to prevent duplicate actions:

```json
{
  "idempotency_key": "trial-end-user-42",
  "type": "http",
  ...
}
```

If you create an action with the same idempotency key:
- If the original is still pending → returns the existing action
- If already executed/cancelled → returns an error

You can also [cancel by idempotency key](../api/cancel-action).

## What's Next

- [Action states](./states) - Understand the lifecycle
- [Retry strategies](./retries) - Handle failures
- [Create an action](../api/create-action) - API reference
