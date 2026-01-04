---
sidebar_position: 2
---

# Create Action

Schedule a new HTTP call or reminder.

```
POST /api/v1/actions
```

## Request Body

### Common Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | `http` or `reminder` |
| `idempotency_key` | string | No | Unique key to prevent duplicates (max 255 chars) |
| `intent` | object | Yes | When to execute (see below) |

### Intent Object

One of these is required:

| Field | Type | Example | Description |
|-------|------|---------|-------------|
| `preset` | string | `"tomorrow"` | Named time preset |
| `delay` | string | `"2h"` | Relative delay |
| `execute_at` | string | `"2025-04-01T09:00:00Z"` | Exact UTC time |
| `timezone` | string | `"America/New_York"` | Timezone for presets/delays |

**Presets:** `tomorrow`, `next_week`, `next_monday` through `next_sunday`, `1h`, `2h`, `4h`, `1d`, `3d`, `1w`

**Delay format:** Number + unit (`m`, `h`, `d`, `w`)

### HTTP Action Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `http_request` | object | Yes | Request configuration |
| `http_request.method` | string | No | HTTP method (default: `POST`) |
| `http_request.url` | string | Yes | Destination URL |
| `http_request.headers` | object | No | Custom headers |
| `http_request.body` | object | No | JSON body |
| `max_attempts` | integer | No | Max delivery attempts (default: 5) |
| `retry_strategy` | string | No | `exponential` or `linear` |
| `webhook_secret` | string | No | Secret for signing requests |

### Reminder Action Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `reminder_message` | string | Yes | Message to send |
| `recipients` | array | Yes | Email addresses or phone numbers |
| `confirmation_mode` | string | No | `any` (default) or `all` |
| `max_snoozes` | integer | No | Max snooze count (default: 3) |
| `snooze_duration` | string | No | Snooze delay (default: `1h`) |
| `expires_after` | string | No | Time until expiry (default: `24h`) |

## Examples

### HTTP Action

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "type": "http",
    "idempotency_key": "trial-end-user-42",
    "intent": {
      "preset": "3d",
      "timezone": "Europe/Paris"
    },
    "http_request": {
      "method": "POST",
      "url": "https://api.example.com/webhooks/trial-expired",
      "headers": {
        "X-Custom-Header": "value"
      },
      "body": {
        "event": "trial_expired",
        "user_id": 42,
        "plan": "pro"
      }
    },
    "max_attempts": 5,
    "retry_strategy": "exponential",
    "webhook_secret": "your-secret-key"
  }'
```

### Reminder Action

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "type": "reminder",
    "idempotency_key": "deploy-approval-abc123",
    "intent": {
      "delay": "30m"
    },
    "reminder_message": "Please approve the production deployment for release v2.1.0",
    "recipients": [
      "tech-lead@example.com",
      "+1234567890"
    ],
    "confirmation_mode": "any",
    "max_snoozes": 2,
    "snooze_duration": "1h",
    "expires_after": "8h"
  }'
```

## Response

### Success (201 Created)

```json
{
  "data": {
    "id": "01234567-89ab-cdef-0123-456789abcdef",
    "type": "http",
    "idempotency_key": "trial-end-user-42",
    "resolution_status": "resolved",
    "execute_at_utc": "2025-01-08T09:00:00Z",
    "attempt_count": 0,
    "max_attempts": 5,
    "retry_strategy": "exponential",
    "created_at": "2025-01-05T10:30:00Z",
    "updated_at": "2025-01-05T10:30:00Z"
  }
}
```

### Validation Error (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "http_request.url": ["The http_request.url field is required when type is http."]
  }
}
```

### Idempotency Conflict (409)

```json
{
  "message": "An action with this idempotency key already exists and has been executed.",
  "id": "01234567-89ab-cdef-0123-456789abcdef"
}
```

## Rate Limits

- **100 actions per hour** per user (Free plan: 10)
- Returns `429 Too Many Requests` when exceeded
