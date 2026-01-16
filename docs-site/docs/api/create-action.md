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
| `type` | string | No | `http` (default) or `reminder` |
| `name` | string | No | Display name (auto-generated if omitted) |
| `idempotency_key` | string | No | Unique key to prevent duplicates (max 255 chars) |
| `intent` | object | Yes* | When to execute (see below) |

*Either `intent` or top-level `execute_at` must be provided.

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
| `message` | string | Yes | Message to send |
| `escalation_rules` | object | Yes | Recipients and delivery options |
| `escalation_rules.recipients` | array | Yes | Email addresses or phone numbers (E.164 format) |
| `escalation_rules.channels` | array | No | `["email"]` (default) or `["email", "sms"]` |
| `escalation_rules.token_expiry_days` | integer | No | Days until response link expires (default: 7) |
| `confirmation_mode` | string | No | `first_response` (default) or `all_required` |
| `max_snoozes` | integer | No | Max snooze count (default: 5) |
| `callback_url` | string | No | URL to receive response webhooks |

## Examples

### HTTP Action

import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

<Tabs groupId="programming-language">
<TabItem value="curl" label="cURL" default>

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "type": "http",
    "idempotency_key": "trial-end-user-42",
    "intent": { "delay": "14d" },
    "http_request": {
      "method": "POST",
      "url": "https://api.example.com/webhooks/trial-expired",
      "body": { "event": "trial_expired", "user_id": 42 }
    }
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
        'type' => 'http',
        'idempotency_key' => 'trial-end-user-42',
        'intent' => ['delay' => '14d'],
        'http_request' => [
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
        "type": "http",
        "idempotency_key": "trial-end-user-42",
        "intent": {"delay": "14d"},
        "http_request": {
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
    type: 'http',
    idempotency_key: 'trial-end-user-42',
    intent: { delay: '14d' },
    http_request: {
      method: 'POST',
      url: 'https://api.example.com/webhooks/trial-expired',
      body: { event: 'trial_expired', user_id: 42 },
    },
  }),
});

const action = await response.json();
```

</TabItem>
<TabItem value="go" label="Go">

```go
package main

import (
    "bytes"
    "encoding/json"
    "net/http"
)

func createAction() (*http.Response, error) {
    payload := map[string]interface{}{
        "type": "http",
        "idempotency_key": "trial-end-user-42",
        "intent": map[string]string{"delay": "14d"},
        "http_request": map[string]interface{}{
            "method": "POST",
            "url": "https://api.example.com/webhooks/trial-expired",
            "body": map[string]interface{}{"event": "trial_expired", "user_id": 42},
        },
    }

    body, _ := json.Marshal(payload)
    req, _ := http.NewRequest("POST", "https://api.callmelater.io/v1/actions", bytes.NewBuffer(body))
    req.Header.Set("Authorization", "Bearer sk_live_...")
    req.Header.Set("Content-Type", "application/json")

    client := &http.Client{}
    return client.Do(req)
}
```

</TabItem>
</Tabs>

### Reminder Action

<Tabs groupId="programming-language">
<TabItem value="curl" label="cURL" default>

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "type": "reminder",
    "idempotency_key": "deploy-approval-abc123",
    "intent": { "delay": "30m" },
    "message": "Please approve the production deployment",
    "escalation_rules": {
      "recipients": ["tech-lead@example.com"],
      "channels": ["email"]
    }
  }'
```

</TabItem>
<TabItem value="php" label="PHP">

```php
<?php
$client = new \GuzzleHttp\Client();
$response = $client->post('https://api.callmelater.io/v1/actions', [
    'headers' => ['Authorization' => 'Bearer sk_live_...'],
    'json' => [
        'type' => 'reminder',
        'idempotency_key' => 'deploy-approval-abc123',
        'intent' => ['delay' => '30m'],
        'message' => 'Please approve the production deployment',
        'escalation_rules' => [
            'recipients' => ['tech-lead@example.com'],
            'channels' => ['email'],
        ],
    ],
]);
```

</TabItem>
<TabItem value="python" label="Python">

```python
import requests

response = requests.post(
    "https://api.callmelater.io/v1/actions",
    headers={"Authorization": "Bearer sk_live_..."},
    json={
        "type": "reminder",
        "idempotency_key": "deploy-approval-abc123",
        "intent": {"delay": "30m"},
        "message": "Please approve the production deployment",
        "escalation_rules": {
            "recipients": ["tech-lead@example.com"],
            "channels": ["email"],
        },
    },
)
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
    type: 'reminder',
    idempotency_key: 'deploy-approval-abc123',
    intent: { delay: '30m' },
    message: 'Please approve the production deployment',
    escalation_rules: {
      recipients: ['tech-lead@example.com'],
      channels: ['email'],
    },
  }),
});
```

</TabItem>
</Tabs>

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
