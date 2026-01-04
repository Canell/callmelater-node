---
sidebar_position: 4
---

# Get Action

Retrieve details of a specific action, including delivery attempts and reminder events.

```
GET /api/v1/actions/{id}
```

## Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Action UUID |

## Example

```bash
curl https://api.callmelater.io/v1/actions/01234567-89ab-cdef-0123-456789abcdef \
  -H "Authorization: Bearer sk_live_..."
```

## Response

### HTTP Action

```json
{
  "data": {
    "id": "01234567-89ab-cdef-0123-456789abcdef",
    "type": "http",
    "idempotency_key": "trial-end-user-42",
    "resolution_status": "executed",
    "execute_at_utc": "2025-01-08T09:00:00Z",
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
    },
    "attempt_count": 2,
    "max_attempts": 5,
    "retry_strategy": "exponential",
    "last_attempt_at": "2025-01-08T09:01:00Z",
    "created_at": "2025-01-05T10:30:00Z",
    "updated_at": "2025-01-08T09:01:00Z",
    "delivery_attempts": [
      {
        "id": "attempt-uuid-1",
        "attempt_number": 1,
        "status": "failed",
        "response_code": 503,
        "response_body": "Service Unavailable",
        "error_message": null,
        "duration_ms": 1523,
        "attempted_at": "2025-01-08T09:00:00Z"
      },
      {
        "id": "attempt-uuid-2",
        "attempt_number": 2,
        "status": "success",
        "response_code": 200,
        "response_body": "{\"received\": true}",
        "error_message": null,
        "duration_ms": 234,
        "attempted_at": "2025-01-08T09:01:00Z"
      }
    ]
  }
}
```

### Reminder Action

```json
{
  "data": {
    "id": "fedcba98-7654-3210-fedc-ba9876543210",
    "type": "reminder",
    "idempotency_key": "deploy-approval-abc123",
    "resolution_status": "executed",
    "execute_at_utc": "2025-01-05T14:00:00Z",
    "reminder_message": "Please approve the production deployment",
    "confirmation_mode": "any",
    "max_snoozes": 3,
    "snoozes_used": 1,
    "created_at": "2025-01-05T09:00:00Z",
    "updated_at": "2025-01-05T15:30:00Z",
    "recipients": [
      {
        "id": "recipient-uuid-1",
        "contact": "ops@example.com",
        "channel": "email",
        "status": "confirmed",
        "responded_at": "2025-01-05T15:30:00Z"
      },
      {
        "id": "recipient-uuid-2",
        "contact": "+1234567890",
        "channel": "sms",
        "status": "pending",
        "responded_at": null
      }
    ],
    "reminder_events": [
      {
        "id": "event-uuid-1",
        "event_type": "sent",
        "recipient": "ops@example.com",
        "channel": "email",
        "occurred_at": "2025-01-05T14:00:00Z"
      },
      {
        "id": "event-uuid-2",
        "event_type": "sent",
        "recipient": "+1234567890",
        "channel": "sms",
        "occurred_at": "2025-01-05T14:00:05Z"
      },
      {
        "id": "event-uuid-3",
        "event_type": "snoozed",
        "recipient": "ops@example.com",
        "occurred_at": "2025-01-05T14:30:00Z"
      },
      {
        "id": "event-uuid-4",
        "event_type": "confirmed",
        "recipient": "ops@example.com",
        "occurred_at": "2025-01-05T15:30:00Z"
      }
    ]
  }
}
```

## Error Responses

### Not Found (404)

```json
{
  "message": "Action not found"
}
```

The action doesn't exist or belongs to another user.
