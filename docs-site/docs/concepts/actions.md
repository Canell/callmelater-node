---
sidebar_position: 1
---

# Actions

Everything in CallMeLater is an **action** — something scheduled to happen in the future.

## Lifecycle at a Glance

Every action moves through these states:

```
scheduled → resolved → executing → executed
                                 ↘ failed
                    ↘ awaiting_response → executed | failed | expired
                    ↘ cancelled
```

Note: `scheduled` is also known as `pending_resolution` for backwards compatibility.

For full details, see [Action States](./states).

## Modes

Actions have two modes:

### Webhook Mode (default)

A webhook action triggers an HTTP call at a scheduled time. This is the default mode. Also known as `immediate` mode for backwards compatibility.

```json
{
  "schedule": {
    "preset": "tomorrow"
  },
  "request": {
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

Use webhook mode for:
- Trial expirations
- Delayed notifications
- Scheduled API calls
- Cleanup tasks
- Follow-up triggers

### Approval Mode

An approval action sends a message to one or more recipients, asking them to respond before any HTTP request is made. Also known as `gated` mode for backwards compatibility.

```json
{
  "mode": "approval",
  "schedule": {
    "wait": "2h"
  },
  "gate": {
    "message": "Please confirm the deployment",
    "recipients": ["ops@example.com", "+1234567890"],
    "channels": ["email", "sms"],
    "confirmation_mode": "first_response"
  }
}
```

Use approval mode for:
- Approval workflows
- Human confirmations
- Check-ins
- Escalation chains

## Scheduling

Every action needs a **schedule** that defines when it should execute. Also known as `intent` for backwards compatibility.

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
  "schedule": {
    "preset": "next_monday",
    "timezone": "America/New_York"
  }
}
```

### Relative Wait

A duration from now. Also known as `delay` for backwards compatibility.

```json
{
  "schedule": {
    "wait": "30m"
  }
}
```

Supported units: `m` (minutes), `h` (hours), `d` (days), `w` (weeks)

### Exact Time

A specific UTC timestamp. Also known as `execute_at` for backwards compatibility.

```json
{
  "schedule": {
    "scheduled_for": "2025-04-01T14:30:00Z"
  }
}
```

:::note Timezone Handling
If no `timezone` is provided in the schedule, **UTC is assumed**. Always specify a timezone for presets like `tomorrow` or `next_monday` if your users expect local time.
:::

## Idempotency

Use `idempotency_key` to prevent duplicate actions:

```json
{
  "idempotency_key": "trial-end-user-42",
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
