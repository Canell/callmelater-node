---
sidebar_position: 4
---

# Templates

Templates let you define reusable action or chain configurations with a unique trigger URL. Each template gets a public endpoint that can be called without API key authentication, making templates ideal for CI/CD pipelines, external integrations, and no-code tools.

## Create Template

```
POST /templates
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Template name (max 255 chars). Supports `{{placeholder}}` syntax. |
| `description` | string | No | Optional description (max 1000 chars) |
| `type` | string | No | `action` (default) or `chain` |
| `mode` | string | Yes* | `webhook` or `approval`. Required when `type` is `action`. |
| `timezone` | string | No | Default timezone (e.g., `America/New_York`) |

**For action templates (`type: "action"`):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `request_config` | object | Yes* | HTTP request configuration. Required for `webhook` mode. |
| `request_config.url` | string | Yes | Destination URL. Supports `{{placeholder}}` syntax. |
| `request_config.method` | string | No | HTTP method (default: `POST`) |
| `request_config.headers` | object | No | Custom headers. Values support `{{placeholder}}`. |
| `request_config.body` | object | No | JSON body. Values support `{{placeholder}}`. |
| `gate_config` | object | Yes** | Gate configuration. Required for `approval` mode. |
| `gate_config.message` | string | Yes | Approval message. Supports `{{placeholder}}`. |
| `gate_config.recipients` | array | Yes | Recipient list. Values support `{{placeholder}}`. |
| `gate_config.timeout` | string | No | Response timeout (default: `7d`) |
| `gate_config.confirmation_mode` | string | No | `first_response` (default) or `all_required` |
| `gate_config.max_snoozes` | integer | No | Max snooze count (default: 5) |
| `max_attempts` | integer | No | Max delivery attempts, 1-10 (default: 5) |
| `retry_strategy` | string | No | `exponential` (default) or `linear` |

**For chain templates (`type: "chain"`):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `chain_steps` | array | Yes | Array of step definitions (min 2, max 20). See [Chains](/api/chains). |
| `chain_error_handling` | string | No | `fail_chain` (default) or `skip_step` |

**Placeholders:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `placeholders` | array | No | Placeholder definitions |
| `placeholders[].name` | string | Yes | Variable name (alphanumeric and underscore) |
| `placeholders[].required` | boolean | No | Whether the value must be provided (default: `false`) |
| `placeholders[].description` | string | No | Human-readable description |
| `placeholders[].default` | string | No | Default value when not provided at trigger time |

**Dedup keys:**

Templates support `dedup_keys` with `{{placeholder}}` interpolation, allowing dynamic grouping at trigger time.

### Example

```bash
curl -X POST https://api.callmelater.io/v1/templates \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Deploy {{service}}",
    "mode": "approval",
    "gate_config": {
      "message": "Deploy {{service}} v{{version}} to {{env}}?",
      "recipients": ["ops@example.com"],
      "timeout": "4h"
    },
    "request_config": {
      "url": "https://deploy.example.com/{{service}}",
      "method": "POST",
      "body": {
        "version": "{{version}}",
        "environment": "{{env}}"
      }
    },
    "placeholders": [
      { "name": "service", "required": true, "description": "Service name" },
      { "name": "version", "required": true, "description": "Version number" },
      { "name": "env", "required": false, "default": "staging", "description": "Target environment" }
    ],
    "dedup_keys": ["deploy:{{service}}:{{env}}"]
  }'
```

### Response (201 Created)

```json
{
  "data": {
    "id": "01abc123-...",
    "name": "Deploy {{service}}",
    "mode": "approval",
    "trigger_url": "https://api.callmelater.io/t/clmt_abc123...",
    "trigger_token": "clmt_abc123...",
    "placeholders": [
      { "name": "service", "required": true, "description": "Service name" },
      { "name": "version", "required": true, "description": "Version number" },
      { "name": "env", "required": false, "default": "staging", "description": "Target environment" }
    ],
    "is_active": true,
    "trigger_count": 0,
    "created_at": "2026-01-15T10:00:00Z"
  }
}
```

---

## Trigger Template

```
POST /t/{trigger_token}
```

This is a **public endpoint** -- no API key authentication is required. The trigger token in the URL authenticates the request.

### Request Body

Send placeholder values as a flat JSON object. Optionally include a `schedule` override.

```json
{
  "service": "api-gateway",
  "version": "2.4.1",
  "env": "production",
  "schedule": {
    "wait": "5m"
  }
}
```

If no `schedule` is provided, the action is created with a minimal delay (approximately 1 second).

### Response (201 Created)

```json
{
  "message": "Action created from template.",
  "data": {
    "id": "01xyz789-...",
    "name": "Deploy api-gateway",
    "mode": "approval",
    "status": "scheduled",
    "template_id": "01abc123-...",
    "created_at": "2026-01-15T14:30:00Z"
  }
}
```

### Rate Limits

| Scope | Limit |
|-------|-------|
| Per token | 60 requests/minute |
| Per IP | 120 requests/minute |

---

## List Templates

```
GET /templates
```

Returns a paginated list of your templates with trigger counts and last-triggered timestamps.

---

## Get Template

```
GET /templates/{id}
```

Returns the full template configuration including placeholders, request/gate config, and trigger statistics.

---

## Update Template

```
PUT /templates/{id}
```

Accepts the same fields as Create. Only provided fields are updated. The trigger URL is not affected.

---

## Delete Template

```
DELETE /templates/{id}
```

Permanently deletes the template. The trigger URL immediately stops working. Returns `204 No Content`.

---

## Regenerate Token

```
POST /templates/{id}/regenerate-token
```

Generates a new trigger token and URL. The previous URL immediately stops working.

### Response

```json
{
  "message": "Trigger token regenerated successfully.",
  "data": {
    "trigger_token": "clmt_newtoken...",
    "trigger_url": "https://api.callmelater.io/t/clmt_newtoken..."
  }
}
```

---

## Toggle Active

```
POST /templates/{id}/toggle-active
```

Enables or disables the template. Inactive templates return `404` when their trigger URL is called.

---

## Get Limits

```
GET /templates/limits
```

Returns the maximum number of templates allowed on your plan and your current count.

### Response

```json
{
  "current": 2,
  "max": 25,
  "remaining": 23,
  "plan": "pro"
}
```

Limits depend on your plan.
