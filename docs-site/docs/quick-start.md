---
sidebar_position: 2
---

# Quick Start

Schedule your first action in 60 seconds.

## 1. Get your API key

Sign up at [callmelater.io](https://callmelater.io) and create an API token from **Settings → API Tokens**.

## 2. Schedule an HTTP call

```bash
curl https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "schedule": { "wait": "5m" },
    "request": {
      "url": "https://your-app.com/webhook",
      "method": "POST",
      "body": { "event": "test", "message": "Hello from CallMeLater" }
    }
  }'
```

This schedules a POST request to your URL in 5 minutes. The response includes the action ID:

```json
{
  "data": {
    "id": "act_abc123...",
    "status": "scheduled",
    "scheduled_for": "2025-06-15T14:35:00Z"
  }
}
```

## 3. Check the status

```bash
curl https://api.callmelater.io/v1/actions/act_abc123 \
  -H "Authorization: Bearer sk_live_..."
```

## 4. Cancel it

```bash
curl -X DELETE https://api.callmelater.io/v1/actions/act_abc123 \
  -H "Authorization: Bearer sk_live_..."
```

## 5. Send an approval reminder

```bash
curl https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "approval",
    "name": "Approve deployment",
    "schedule": { "wait": "1s" },
    "gate": {
      "message": "Ready to deploy v2.1 to production?",
      "recipients": ["ops@example.com"],
      "buttons": ["Approve", "Reject"]
    },
    "callback_url": "https://your-app.com/webhook"
  }'
```

When someone responds, CallMeLater sends the response to your `callback_url`.

## Next steps

- **[Node.js SDK](/sdks/nodejs)** — Fluent TypeScript API, install with `npm install callmelater`
- **[Laravel SDK](/sdks/laravel)** — Facades and fluent builders, install with `composer require callmelater/laravel`
- **[Core Concepts](/concepts/actions)** — Scheduling, lifecycle states, idempotency
- **[API Reference](/api/authentication)** — Full endpoint documentation
