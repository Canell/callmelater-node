---
sidebar_position: 2
---

# Create Action

Schedule a new HTTP call or gated reminder.

```
POST /api/v1/actions
```

## Request Body

### Common Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `mode` | string | No | `immediate` (default) or `gated` |
| `name` | string | No | Display name (auto-generated if omitted) |
| `description` | string | No | Optional description (max 1000 chars) |
| `timezone` | string | No | Timezone for scheduling (e.g., `America/New_York`) |
| `idempotency_key` | string | No | Unique key to prevent duplicates (max 255 chars) |
| `callback_url` | string | No | URL to receive status webhooks |
| `intent` | object | Yes* | When to execute (see below) |

*Either `intent` or top-level `execute_at` must be provided.

### Scheduling (Intent)

One of these is required:

| Field | Type | Example | Description |
|-------|------|---------|-------------|
| `intent.preset` | string | `"tomorrow"` | Named time preset |
| `intent.delay` | string | `"2h"` | Relative delay |
| `execute_at` | string | `"2025-04-01T09:00:00Z"` | Exact UTC time (top-level) |

**Presets:** `tomorrow`, `next_week`, `next_monday` through `next_sunday`, `1h`, `2h`, `4h`, `1d`, `3d`, `1w`

**Delay format:** Number + unit (`m` minutes, `h` hours, `d` days, `w` weeks)

### Immediate Mode Fields (HTTP Actions)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `request` | object | Yes | HTTP request configuration |
| `request.url` | string | Yes | Destination URL (https required for production) |
| `request.method` | string | No | HTTP method (default: `POST`) |
| `request.headers` | object | No | Custom headers |
| `request.body` | object | No | JSON body |
| `request.timeout` | integer | No | Request timeout in seconds (1-120, default: 30) |
| `max_attempts` | integer | No | Max delivery attempts (1-10, default: 5) |
| `retry_strategy` | string | No | `exponential` (default) or `linear` |
| `webhook_secret` | string | No | Secret for signing requests |

### Gated Mode Fields (Human Confirmations)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `gate` | object | Yes | Gate configuration |
| `gate.message` | string | Yes | Message to display (max 5000 chars) |
| `gate.recipients` | array | Yes | Email addresses, phone numbers (E.164), or team member IDs |
| `gate.channels` | array | No | `["email"]` (default) or `["email", "sms"]` |
| `gate.timeout` | string | No | Response timeout (e.g., `4h`, `7d`, `1w`) - default: `7d` |
| `gate.on_timeout` | string | No | `cancel`, `expire` (default), or `approve` |
| `gate.max_snoozes` | integer | No | Max snooze count (0-10, default: 5) |
| `gate.confirmation_mode` | string | No | `first_response` (default) or `all_required` |
| `gate.escalation` | object | No | Escalation configuration |
| `gate.escalation.after_hours` | number | No | Hours before escalating (min: 0.5) |
| `gate.escalation.contacts` | array | No | Email addresses for escalation |
| `request` | object | No | Optional HTTP request to execute after approval |

### Coordination Keys (Action Grouping)

Group related actions and control their behavior:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `coordination_keys` | array | No | Keys for grouping (max 10, alphanumeric with `_:.-`) |
| `coordination` | object | No | Coordination behavior configuration |
| `coordination.on_create` | string | No | Behavior when creating: `replace_existing`, `skip_if_exists` |
| `coordination.on_execute` | object | No | Execution-time conditions |
| `coordination.on_execute.condition` | string | No | `skip_if_previous_pending`, `execute_if_previous_failed`, `execute_if_previous_succeeded`, `wait_for_previous` |
| `coordination.on_execute.on_condition_not_met` | string | No | `cancel` (default), `reschedule`, `fail` |
| `coordination.on_execute.reschedule_delay` | integer | No | Seconds to wait before retry (60-86400, default: 300) |
| `coordination.on_execute.max_reschedules` | integer | No | Max reschedule attempts (1-100, default: 10) |

**on_create behaviors:**
- `replace_existing`: Cancel existing non-terminal actions with matching keys and link them to the new action
- `skip_if_exists`: Return existing action instead of creating new one (returns 200 with `meta.skipped: true`)

**on_execute conditions:**
- `skip_if_previous_pending`: Cancel if another non-terminal action with same key exists
- `execute_if_previous_failed`: Only execute if the previous action (same key) failed
- `execute_if_previous_succeeded`: Only execute if the previous action succeeded
- `wait_for_previous`: Reschedule until previous action completes (action chaining)

## Examples

### Immediate Mode (HTTP Action)

import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

<Tabs groupId="programming-language">
<TabItem value="curl" label="cURL" default>

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "immediate",
    "name": "Trial expiration webhook",
    "idempotency_key": "trial-end-user-42",
    "intent": { "delay": "14d" },
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

</TabItem>
<TabItem value="php" label="PHP">

```php
<?php
use GuzzleHttp\Client;

$client = new Client();
$response = $client->post('https://api.callmelater.io/v1/actions', [
    'headers' => [
        'Authorization' => 'Bearer sk_live_...',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'mode' => 'immediate',
        'name' => 'Trial expiration webhook',
        'idempotency_key' => 'trial-end-user-42',
        'intent' => ['delay' => '14d'],
        'request' => [
            'method' => 'POST',
            'url' => 'https://api.example.com/webhooks/trial-expired',
            'body' => ['event' => 'trial_expired', 'user_id' => 42],
        ],
    ],
]);

$action = json_decode($response->getBody(), true);
```

</TabItem>
<TabItem value="python" label="Python">

```python
import requests

response = requests.post(
    "https://api.callmelater.io/v1/actions",
    headers={
        "Authorization": "Bearer sk_live_...",
        "Content-Type": "application/json",
    },
    json={
        "mode": "immediate",
        "name": "Trial expiration webhook",
        "idempotency_key": "trial-end-user-42",
        "intent": {"delay": "14d"},
        "request": {
            "method": "POST",
            "url": "https://api.example.com/webhooks/trial-expired",
            "body": {"event": "trial_expired", "user_id": 42},
        },
    },
)

action = response.json()
```

</TabItem>
<TabItem value="javascript" label="JavaScript">

```javascript
const response = await fetch('https://api.callmelater.io/v1/actions', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer sk_live_...',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    mode: 'immediate',
    name: 'Trial expiration webhook',
    idempotency_key: 'trial-end-user-42',
    intent: { delay: '14d' },
    request: {
      method: 'POST',
      url: 'https://api.example.com/webhooks/trial-expired',
      body: { event: 'trial_expired', user_id: 42 },
    },
  }),
});

const action = await response.json();
```

</TabItem>
</Tabs>

### Gated Mode (Human Confirmation)

<Tabs groupId="programming-language">
<TabItem value="curl" label="cURL" default>

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "gated",
    "name": "Production deployment approval",
    "idempotency_key": "deploy-approval-abc123",
    "intent": { "delay": "30m" },
    "gate": {
      "message": "Please approve the production deployment for release v2.1.0",
      "recipients": ["tech-lead@example.com", "+15551234567"],
      "channels": ["email", "sms"],
      "timeout": "4h",
      "on_timeout": "cancel",
      "max_snoozes": 3
    },
    "callback_url": "https://api.example.com/webhooks/approval-response"
  }'
```

</TabItem>
<TabItem value="php" label="PHP">

```php
<?php
$response = $client->post('https://api.callmelater.io/v1/actions', [
    'headers' => ['Authorization' => 'Bearer sk_live_...'],
    'json' => [
        'mode' => 'gated',
        'name' => 'Production deployment approval',
        'idempotency_key' => 'deploy-approval-abc123',
        'intent' => ['delay' => '30m'],
        'gate' => [
            'message' => 'Please approve the production deployment',
            'recipients' => ['tech-lead@example.com'],
            'channels' => ['email'],
            'timeout' => '4h',
            'on_timeout' => 'cancel',
        ],
    ],
]);
```

</TabItem>
</Tabs>

### Gated Mode with HTTP Request (Approval + Action)

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "gated",
    "name": "Approve and deploy",
    "intent": { "preset": "tomorrow" },
    "gate": {
      "message": "Approve deployment to production?",
      "recipients": ["ops@example.com"],
      "timeout": "2h"
    },
    "request": {
      "url": "https://api.example.com/deploy",
      "method": "POST",
      "body": { "environment": "production" }
    }
  }'
```

When approved, the HTTP request executes automatically.

### With Coordination Keys

```bash
# Replace any existing deployment action for this service
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Deploy API v2.1",
    "intent": { "delay": "1h" },
    "coordination_keys": ["deploy:api-service"],
    "coordination": {
      "on_create": "replace_existing"
    },
    "request": {
      "url": "https://ci.example.com/deploy",
      "body": { "service": "api", "version": "2.1" }
    }
  }'
```

### Action Chaining (wait_for_previous)

```bash
# This action waits for any previous action with the same key to complete
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Database migration step 2",
    "intent": { "delay": "5m" },
    "coordination_keys": ["migration:db-upgrade"],
    "coordination": {
      "on_execute": {
        "condition": "wait_for_previous",
        "on_condition_not_met": "reschedule",
        "reschedule_delay": 60,
        "max_reschedules": 30
      }
    },
    "request": {
      "url": "https://api.example.com/migrations/step2"
    }
  }'
```

## Response

### Success (201 Created)

```json
{
  "data": {
    "id": "01234567-89ab-cdef-0123-456789abcdef",
    "name": "Trial expiration webhook",
    "description": null,
    "mode": "immediate",
    "status": "pending_resolution",
    "timezone": "UTC",
    "execute_at": "2025-01-19T10:30:00Z",
    "idempotency_key": "trial-end-user-42",
    "coordination_keys": [],
    "attempt_count": 0,
    "max_attempts": 5,
    "retry_strategy": "exponential",
    "created_at": "2025-01-05T10:30:00Z",
    "updated_at": "2025-01-05T10:30:00Z"
  }
}
```

### Skipped (200 OK) - When using skip_if_exists

```json
{
  "data": {
    "id": "existing-action-id",
    "name": "Existing action",
    "status": "resolved",
    ...
  },
  "meta": {
    "skipped": true,
    "reason": "existing_action_found"
  }
}
```

### Replaced Actions (201 Created)

When using `replace_existing`:

```json
{
  "data": {
    "id": "new-action-id",
    ...
  },
  "meta": {
    "replaced_action_ids": ["old-action-1", "old-action-2"]
  }
}
```

### Validation Error (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "request.url": ["The request.url field is required."],
    "coordination_keys": ["At least one coordination_key is required when using coordination.on_create."]
  }
}
```

### Idempotency Conflict (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "idempotency_key": ["This idempotency key has already been used."]
  }
}
```

### Plan Limit Exceeded (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "limit": ["You have reached your monthly limit of 100 actions. Upgrade your plan to create more actions."]
  }
}
```

## Notes

- Actions in `immediate` mode require the `request` object
- Actions in `gated` mode require the `gate` object
- The `request` object is optional for `gated` mode - if provided, the HTTP call executes after approval
- Coordination keys are useful for managing related actions (e.g., deployments, migrations)
- Domain verification may be required for high-volume usage to a specific domain
