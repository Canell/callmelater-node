---
sidebar_position: 4
---

# Get Action

Retrieve details of a specific action, including delivery attempts, reminder events, and related actions.

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

### Immediate Mode Action

```json
{
  "data": {
    "id": "01234567-89ab-cdef-0123-456789abcdef",
    "name": "Trial expiration webhook",
    "description": "Triggers when user 42's trial ends",
    "mode": "immediate",
    "status": "executed",
    "timezone": "America/New_York",
    "execute_at": "2025-01-08T09:00:00Z",
    "executed_at": "2025-01-08T09:01:00Z",
    "idempotency_key": "trial-end-user-42",
    "coordination_keys": ["user:42", "trial-expiration"],
    "coordination_config": {
      "on_create": "replace_existing"
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
    },
    "attempt_count": 2,
    "max_attempts": 5,
    "retry_strategy": "exponential",
    "created_at": "2025-01-05T10:30:00Z",
    "updated_at": "2025-01-08T09:01:00Z",
    "delivery_attempts": [
      {
        "id": "attempt-uuid-1",
        "attempt_number": 1,
        "status": "failed",
        "failure_category": "server_error",
        "response_code": 503,
        "response_body_preview": "Service Unavailable",
        "error_message": null,
        "duration_ms": 1523,
        "target_domain": "api.example.com",
        "attempted_at": "2025-01-08T09:00:00Z"
      },
      {
        "id": "attempt-uuid-2",
        "attempt_number": 2,
        "status": "success",
        "failure_category": "success",
        "response_code": 200,
        "response_body_preview": "{\"received\": true}",
        "error_message": null,
        "duration_ms": 234,
        "target_domain": "api.example.com",
        "attempted_at": "2025-01-08T09:01:00Z"
      }
    ],
    "execution_cycles": [
      {
        "id": "cycle-uuid-1",
        "cycle_number": 1,
        "trigger": "scheduled",
        "started_at": "2025-01-08T09:00:00Z",
        "completed_at": "2025-01-08T09:01:00Z",
        "outcome": "success"
      }
    ],
    "related_actions": [
      {
        "id": "previous-action-id",
        "name": "Trial expiration webhook (replaced)",
        "status": "cancelled",
        "created_at": "2025-01-04T10:00:00Z"
      }
    ]
  }
}
```

### Gated Mode Action

```json
{
  "data": {
    "id": "fedcba98-7654-3210-fedc-ba9876543210",
    "name": "Production deployment approval",
    "description": null,
    "mode": "gated",
    "status": "executed",
    "timezone": "UTC",
    "execute_at": "2025-01-05T14:00:00Z",
    "executed_at": "2025-01-05T15:30:00Z",
    "gate_passed_at": "2025-01-05T15:30:00Z",
    "idempotency_key": "deploy-approval-abc123",
    "coordination_keys": [],
    "gate": {
      "message": "Please approve the production deployment",
      "recipients": ["ops@example.com", "+1234567890"],
      "channels": ["email", "sms"],
      "timeout": "4h",
      "on_timeout": "cancel",
      "max_snoozes": 3,
      "confirmation_mode": "first_response"
    },
    "snooze_count": 1,
    "callback_url": "https://api.example.com/webhooks/response",
    "token_expires_at": "2025-01-12T14:00:00Z",
    "created_at": "2025-01-05T09:00:00Z",
    "updated_at": "2025-01-05T15:30:00Z",
    "recipients": [
      {
        "id": "recipient-uuid-1",
        "contact": "ops@example.com",
        "channel": "email",
        "status": "confirmed",
        "responded_at": "2025-01-05T15:30:00Z",
        "response_comment": "Looks good, approved!",
        "team_member": {
          "id": "member-uuid",
          "name": "John Ops",
          "email": "ops@example.com"
        }
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
        "channel": "email",
        "recipient_contact": "ops@example.com",
        "occurred_at": "2025-01-05T14:00:00Z"
      },
      {
        "id": "event-uuid-2",
        "event_type": "sent",
        "channel": "sms",
        "recipient_contact": "+1234567890",
        "occurred_at": "2025-01-05T14:00:05Z"
      },
      {
        "id": "event-uuid-3",
        "event_type": "snoozed",
        "channel": "email",
        "recipient_contact": "ops@example.com",
        "snooze_until": "2025-01-05T15:00:00Z",
        "occurred_at": "2025-01-05T14:30:00Z"
      },
      {
        "id": "event-uuid-4",
        "event_type": "confirmed",
        "channel": "email",
        "recipient_contact": "ops@example.com",
        "comment": "Looks good, approved!",
        "occurred_at": "2025-01-05T15:30:00Z"
      }
    ]
  }
}
```

### Failed Action (with retry info)

```json
{
  "data": {
    "id": "failed-action-id",
    "name": "Failed webhook",
    "mode": "immediate",
    "status": "failed",
    "failure_reason": "Server error (503) after 5 attempts",
    "attempt_count": 5,
    "max_attempts": 5,
    "can_retry": true,
    "manual_retry_count": 0,
    "last_manual_retry_at": null,
    ...
  }
}
```

### Replaced Action

When an action was replaced by another via coordination:

```json
{
  "data": {
    "id": "old-action-id",
    "name": "Old deployment",
    "status": "cancelled",
    "replaced_by_action_id": "new-action-id",
    "replaced_by": {
      "id": "new-action-id",
      "name": "New deployment",
      "status": "resolved"
    },
    ...
  }
}
```

## Response Fields

### Common Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Action UUID |
| `name` | string | Display name |
| `description` | string | Optional description |
| `mode` | string | `immediate` or `gated` |
| `status` | string | Current status (see States) |
| `timezone` | string | Timezone used for scheduling |
| `execute_at` | string | Scheduled execution time (ISO 8601) |
| `executed_at` | string | Actual execution time (if executed) |
| `idempotency_key` | string | Unique key for deduplication |
| `coordination_keys` | array | Coordination keys for grouping |
| `coordination_config` | object | Coordination configuration (if set) |
| `created_at` | string | Creation timestamp |
| `updated_at` | string | Last update timestamp |

### Immediate Mode Fields

| Field | Type | Description |
|-------|------|-------------|
| `request` | object | HTTP request configuration |
| `attempt_count` | integer | Number of delivery attempts |
| `max_attempts` | integer | Maximum attempts allowed |
| `retry_strategy` | string | `exponential` or `linear` |
| `next_retry_at` | string | Next retry time (if retrying) |
| `delivery_attempts` | array | List of delivery attempts |

### Gated Mode Fields

| Field | Type | Description |
|-------|------|-------------|
| `gate` | object | Gate configuration |
| `gate_passed_at` | string | When gate was approved |
| `snooze_count` | integer | Number of snoozes used |
| `callback_url` | string | Response webhook URL |
| `token_expires_at` | string | Response token expiry |
| `recipients` | array | Recipient statuses |
| `reminder_events` | array | Reminder event timeline |

### Failed Action Fields

| Field | Type | Description |
|-------|------|-------------|
| `failure_reason` | string | Human-readable failure description |
| `can_retry` | boolean | Whether manual retry is available |
| `manual_retry_count` | integer | Number of manual retries used |
| `last_manual_retry_at` | string | Last manual retry timestamp |

### Coordination Fields

| Field | Type | Description |
|-------|------|-------------|
| `replaced_by_action_id` | string | ID of replacement action |
| `replaced_by` | object | Summary of replacement action |
| `related_actions` | array | Other actions sharing coordination keys |
| `coordination_reschedule_count` | integer | Times rescheduled due to conditions |

## Error Responses

### Not Found (404)

```json
{
  "message": "Action not found"
}
```

The action doesn't exist or belongs to another account.
