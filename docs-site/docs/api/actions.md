---
sidebar_position: 2
---

# Actions

Actions are the core primitive in CallMeLater. An action is either a scheduled HTTP request (**webhook** mode) or a human approval request (**approval** mode) that executes at a specified time.

## Create Action

```
POST /actions
```

### Common Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `mode` | string | No | `webhook` (default) or `approval` |
| `name` | string | No | Display name (auto-generated if omitted) |
| `description` | string | No | Optional description (max 1000 chars) |
| `timezone` | string | No | IANA timezone for scheduling (e.g., `America/New_York`). Defaults to `UTC`. |
| `idempotency_key` | string | No | Unique key to prevent duplicate creation (max 255 chars) |
| `callback_url` | string | No | URL to receive lifecycle events (executed, failed, expired) |

### Schedule Fields

At least one scheduling field is required.

| Field | Type | Example | Description |
|-------|------|---------|-------------|
| `schedule.preset` | string | `"tomorrow"` | Named time preset |
| `schedule.wait` | string | `"2h"` | Relative wait duration from now |
| `scheduled_for` | string | `"2026-04-01T09:00:00Z"` | Exact UTC timestamp (top-level field) |

**Presets:** `tomorrow`, `next_week`, `next_monday` through `next_sunday`, `1h`, `2h`, `4h`, `1d`, `3d`, `1w`

**Wait format:** Number followed by a unit -- `m` (minutes), `h` (hours), `d` (days), `w` (weeks). Examples: `30m`, `2h`, `14d`, `1w`.

### Webhook Mode Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `request` | object | Yes | HTTP request configuration |
| `request.url` | string | Yes | Destination URL (HTTPS required in production) |
| `request.method` | string | No | HTTP method (default: `POST`) |
| `request.headers` | object | No | Custom headers as key-value pairs |
| `request.body` | object | No | JSON request body |
| `max_attempts` | integer | No | Maximum delivery attempts, 1-10 (default: 5) |
| `retry_strategy` | string | No | `exponential` (default) or `linear` |
| `webhook_secret` | string | No | Secret used to sign the outgoing request |

### Approval Mode Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `gate` | object | Yes | Approval gate configuration |
| `gate.message` | string | Yes | Message displayed to recipients (max 5000 chars) |
| `gate.recipients` | array | Yes | Email addresses, phone numbers (E.164), or contact IDs |
| `gate.channels` | array | No | Delivery channels: `["email"]` (default) or `["email", "sms"]` |
| `gate.confirmation_mode` | string | No | `first_response` (default) or `all_required` |
| `gate.max_snoozes` | integer | No | Maximum snooze count, 0-10 (default: 5) |
| `gate.timeout` | string | No | Response timeout, e.g. `4h`, `7d`, `1w` (default: `7d`) |
| `gate.escalation_contacts` | array | No | Email addresses for escalation |
| `gate.escalation_after_hours` | number | No | Hours before escalating (min: 0.5) |
| `gate.attachments` | array | No | File attachments for the approval message |
| `request` | object | No | Optional HTTP request to execute after approval is granted |

### Dedup Keys Fields

Group related actions and control their behavior with dedup keys.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `dedup_keys` | array | No | Grouping keys (max 10). Alphanumeric plus `_`, `:`, `.`, `-`. |
| `coordination` | object | No | Dedup behavior configuration |
| `coordination.on_create` | string | No | `skip_if_exists` or `cancel_and_replace` |
| `coordination.on_execute` | object | No | Execution-time conditions |
| `coordination.on_execute.condition` | string | No | `skip_if_previous_pending`, `execute_if_previous_failed`, `execute_if_previous_succeeded`, `wait_for_previous` |
| `coordination.on_execute.on_condition_not_met` | string | No | `cancel` (default), `reschedule`, `fail` |
| `coordination.on_execute.reschedule_delay` | integer | No | Seconds before retry, 60-86400 (default: 300) |
| `coordination.on_execute.max_reschedules` | integer | No | Max reschedule attempts, 1-100 (default: 10) |

**on_create behaviors:**

- `skip_if_exists` -- Return the existing non-terminal action instead of creating a new one. The response uses status 200 with `meta.skipped: true`.
- `cancel_and_replace` -- Cancel all existing non-terminal actions sharing the same dedup keys, then create the new action.

**on_execute conditions:**

- `skip_if_previous_pending` -- Cancel this action if another non-terminal action with the same key exists.
- `execute_if_previous_failed` -- Only execute if the most recent action with the same key failed.
- `execute_if_previous_succeeded` -- Only execute if the most recent action with the same key succeeded.
- `wait_for_previous` -- Reschedule until the previous action with the same key reaches a terminal state.

### Example: Webhook Mode

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "webhook",
    "name": "Trial expiration webhook",
    "idempotency_key": "trial-end-user-42",
    "schedule": { "wait": "14d" },
    "request": {
      "method": "POST",
      "url": "https://api.example.com/webhooks/trial-expired",
      "headers": { "X-Custom-Header": "value" },
      "body": { "event": "trial_expired", "user_id": 42 }
    },
    "max_attempts": 5,
    "retry_strategy": "exponential"
  }'
```

### Example: Approval Mode

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "approval",
    "name": "Production deployment approval",
    "idempotency_key": "deploy-approval-abc123",
    "schedule": { "wait": "30m" },
    "gate": {
      "message": "Please approve the production deployment for release v2.1.0",
      "recipients": ["tech-lead@example.com", "+15551234567"],
      "channels": ["email", "sms"],
      "timeout": "4h",
      "max_snoozes": 3
    },
    "callback_url": "https://api.example.com/webhooks/approval-response"
  }'
```

### Responses

**201 Created**

```json
{
  "data": {
    "id": "01234567-89ab-cdef-0123-456789abcdef",
    "name": "Trial expiration webhook",
    "description": null,
    "mode": "webhook",
    "status": "scheduled",
    "timezone": "UTC",
    "scheduled_for": "2026-02-04T10:30:00Z",
    "idempotency_key": "trial-end-user-42",
    "dedup_keys": [],
    "attempt_count": 0,
    "max_attempts": 5,
    "retry_strategy": "exponential",
    "created_at": "2026-01-21T10:30:00Z",
    "updated_at": "2026-01-21T10:30:00Z"
  }
}
```

**200 Skipped** (when `coordination.on_create` is `skip_if_exists` and a matching action exists)

```json
{
  "data": {
    "id": "existing-action-id",
    "name": "Existing action",
    "status": "scheduled"
  },
  "meta": {
    "skipped": true,
    "reason": "existing_action_found"
  }
}
```

**422 Validation Error**

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "request.url": ["The request.url field is required."]
  }
}
```

---

## List Actions

```
GET /actions
```

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | -- | Filter by status: `scheduled`, `resolved`, `executing`, `awaiting_response`, `executed`, `failed`, `cancelled`, `expired` |
| `mode` | string | -- | Filter by mode: `webhook` or `approval` |
| `search` | string | -- | Search in name and description |
| `dedup_key` | string | -- | Filter by dedup key |
| `per_page` | integer | 15 | Results per page (max 100) |
| `page` | integer | 1 | Page number |

### Response

```json
{
  "data": [
    {
      "id": "01234567-89ab-cdef-0123-456789abcdef",
      "name": "Trial expiration webhook",
      "mode": "webhook",
      "status": "scheduled",
      "scheduled_for": "2026-02-04T10:30:00Z",
      "idempotency_key": "trial-end-user-42",
      "dedup_keys": [],
      "created_at": "2026-01-21T10:30:00Z",
      "updated_at": "2026-01-21T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "total": 72
  }
}
```

---

## Get Action

```
GET /actions/{id}
```

Returns the full action detail including delivery history and approval events.

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Action UUID |
| `name` | string | Display name |
| `mode` | string | `webhook` or `approval` |
| `status` | string | Current status |
| `scheduled_for` | string | Scheduled execution time (ISO 8601) |
| `executed_at` | string | Actual execution time (if completed) |
| `request` | object | HTTP request config (webhook mode) |
| `gate` | object | Approval gate config (approval mode) |
| `delivery_attempts` | array | HTTP delivery attempt log (webhook mode) |
| `reminder_events` | array | Approval event timeline (approval mode) |
| `dedup_keys` | array | Dedup keys assigned to this action |
| `created_at` | string | Creation timestamp |
| `updated_at` | string | Last update timestamp |

---

## Cancel Action

```
DELETE /actions/{id}
```

Cancel a pending action before it executes.

**Cancellable statuses:** `scheduled`, `resolved`, `awaiting_response`

Actions that have already reached a terminal status (`executed`, `failed`, `expired`) cannot be cancelled.

### Response

**200 OK**

```json
{
  "data": {
    "id": "01234567-89ab-cdef-0123-456789abcdef",
    "name": "Trial expiration webhook",
    "status": "cancelled"
  }
}
```

### Cancel by Idempotency Key

You can also cancel an action using its idempotency key instead of the UUID:

```
DELETE /actions
```

```json
{
  "idempotency_key": "trial-end-user-42"
}
```

---

## Retry Action

```
POST /actions/{id}/retry
```

Manually retry a failed action. This resets the attempt counter and creates a new execution cycle.

### Requirements

- The action must be in `failed` status
- The action must have an HTTP request configuration (webhook mode)

### Response

**200 OK**

```json
{
  "data": {
    "id": "01234567-89ab-cdef-0123-456789abcdef",
    "name": "Failed webhook",
    "status": "resolved",
    "attempt_count": 0,
    "manual_retry_count": 1
  }
}
```

**422 Unprocessable Entity** (if the action is not in `failed` status or has no HTTP config)

---

## List Coordination Keys

```
GET /coordination-keys
```

Returns a list of all dedup keys used across your account. Use this to discover keys, then filter actions by a specific key using the `dedup_key` parameter on the [List Actions](#list-actions) endpoint.

### Response

```json
{
  "keys": [
    "deploy:api-service",
    "deploy:web-app",
    "migration:db-upgrade",
    "user:42:trial"
  ]
}
```
