---
sidebar_position: 1
slug: /
---

# Getting Started

CallMeLater lets you schedule future actions and confirmations you can actually rely on.

## Quick Start

Send your first scheduled webhook in 60 seconds.

### 1. Get your API key

Sign up at [callmelater.io](https://callmelater.io) and create an API token from your dashboard.

### 2. Schedule an action

```bash
curl https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "intent": { "preset": "tomorrow" },
    "request": {
      "url": "https://your-app.com/webhook",
      "body": {
        "event": "scheduled_task",
        "data": "your payload"
      }
    }
  }'
```

That's the minimal payload. The `mode` defaults to `immediate`, and your webhook secret is used automatically.

### 3. That's it

CallMeLater will:
- Deliver the request at the scheduled time
- Retry automatically if it fails
- Show you exactly what happened

## What can you do?

### Schedule HTTP calls

Trigger webhooks minutes, days, or months in the future:
- Trial expirations
- Delayed notifications
- Cleanup jobs
- Follow-up reminders

[Learn more about HTTP actions →](./concepts/actions)

### Send human reminders

Ask someone to confirm, decline, or snooze:
- Approval workflows
- Check-ins
- Escalation chains

**Note:** Reminders collect human responses — they don't automatically execute your webhook. When someone responds, CallMeLater calls your callback URL with the response details so your system can decide what to do next.

[Learn more about reminders →](./concepts/reminders)

## Key Features

| Feature | Description |
|---------|-------------|
| **Automatic retries** | Exponential backoff when requests fail |
| **Idempotency** | Prevent duplicates with idempotency keys |
| **Webhook signatures** | HMAC-SHA256 signed requests |
| **Flexible scheduling** | Presets, delays, or exact times |
| **Full visibility** | See every attempt and response |

## Next Steps

- [Understand actions](./concepts/actions) - The core concept
- [API authentication](./api/authentication) - Set up your tokens
- [Create your first action](./api/create-action) - Full API reference
