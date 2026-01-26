---
sidebar_position: 7
---

# Action Templates

Create reusable action configurations with unique callable URLs. Templates let you define actions once and trigger them with a simple POST request, optionally passing dynamic values via placeholders.

## Why Templates?

- **No API keys needed for triggering** - Each template has a unique URL that can be called without authentication
- **Reusable configurations** - Define request settings, gate configurations, and retry behavior once
- **Dynamic values** - Use `{{placeholder}}` syntax to inject values at trigger time
- **Perfect for CI/CD** - Trigger deployments or approval workflows from scripts without managing API tokens

## Plan Limits

| Plan | Max Templates |
|------|---------------|
| Free | 2 |
| Pro | 20 |
| Business | 200 |

---

## Create Template

```
POST /api/v1/templates
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Template name (max 255 chars) |
| `description` | string | No | Optional description (max 1000 chars) |
| `mode` | string | Yes | `immediate` or `gated` |
| `timezone` | string | No | Default timezone (e.g., `America/New_York`) |
| `request_config` | object | Yes* | HTTP request configuration |
| `gate_config` | object | Yes** | Gate configuration for gated mode |
| `max_attempts` | integer | No | Max delivery attempts (1-10, default: 5) |
| `retry_strategy` | string | No | `exponential` (default) or `linear` |
| `placeholders` | array | No | Placeholder definitions |
| `default_coordination_keys` | array | No | Default coordination keys |
| `coordination_config` | object | No | Coordination behavior settings |

*Required for `immediate` mode
**Required for `gated` mode

### Request Configuration

```json
{
  "request_config": {
    "url": "https://api.example.com/{{service}}/deploy",
    "method": "POST",
    "headers": {
      "Authorization": "Bearer {{api_token}}"
    },
    "body": {
      "version": "{{version}}",
      "environment": "{{env}}"
    }
  }
}
```

### Gate Configuration

```json
{
  "gate_config": {
    "message": "Deploy {{service}} v{{version}} to {{env}}?",
    "recipients": ["{{approver}}"],
    "timeout": "4h",
    "on_timeout": "expire",
    "confirmation_mode": "first_response",
    "max_snoozes": 3
  }
}
```

### Placeholder Definitions

Define variables that can be passed when triggering:

```json
{
  "placeholders": [
    {
      "name": "service",
      "required": true,
      "description": "Service name to deploy"
    },
    {
      "name": "version",
      "required": true,
      "description": "Version number"
    },
    {
      "name": "env",
      "required": false,
      "default": "staging",
      "description": "Target environment"
    }
  ]
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Variable name (alphanumeric + underscore) |
| `required` | boolean | No | Whether value must be provided (default: false) |
| `default` | string | No | Default value if not provided |
| `description` | string | No | Description for documentation |

### Example: Create Deployment Template

```bash
curl -X POST https://callmelater.io/api/v1/templates \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Deploy {{service}}",
    "mode": "gated",
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
      {"name": "service", "required": true},
      {"name": "version", "required": true},
      {"name": "env", "required": false, "default": "staging"}
    ]
  }'
```

### Response

```json
{
  "data": {
    "id": "01abc123-...",
    "name": "Deploy {{service}}",
    "mode": "gated",
    "trigger_url": "https://callmelater.io/t/clmt_abc123...",
    "trigger_token": "clmt_abc123...",
    "placeholders": [...],
    "is_active": true,
    "trigger_count": 0,
    "created_at": "2025-01-15T10:00:00Z"
  }
}
```

---

## Trigger Template

Trigger a template to create an action. This is a **public endpoint** - no API key required, authenticated by the template token.

```
POST /t/{trigger_token}
```

### Request Body

Pass placeholder values and optional scheduling:

```json
{
  "service": "api-gateway",
  "version": "2.4.1",
  "env": "production",
  "intent": {
    "delay": "5m"
  }
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `{placeholder}` | any | Varies | Values for defined placeholders |
| `intent` | object | No | Scheduling (default: 1 second delay) |
| `execute_at` | string | No | Exact execution time (ISO 8601) |
| `coordination_keys` | array | No | Additional coordination keys |

### Example: Trigger from CI/CD

```bash
# No API key needed - just the template URL
curl -X POST https://callmelater.io/t/clmt_abc123... \
  -H "Content-Type: application/json" \
  -d '{
    "service": "api-gateway",
    "version": "2.4.1",
    "env": "production"
  }'
```

### Response

Returns the created action:

```json
{
  "message": "Action created from template.",
  "data": {
    "id": "01xyz789-...",
    "name": "Deploy api-gateway",
    "mode": "gated",
    "status": "pending_resolution",
    "template_id": "01abc123-...",
    "created_at": "2025-01-15T14:30:00Z"
  }
}
```

### Errors

| Status | Error | Description |
|--------|-------|-------------|
| 404 | `not_found` | Invalid token or inactive template |
| 422 | `validation_error` | Missing required placeholders |
| 429 | `quota_exceeded` | Account action quota exceeded |

---

## List Templates

```
GET /api/v1/templates
```

### Response

```json
{
  "data": [
    {
      "id": "01abc123-...",
      "name": "Deploy {{service}}",
      "mode": "gated",
      "trigger_url": "https://callmelater.io/t/clmt_abc123...",
      "is_active": true,
      "trigger_count": 42,
      "last_triggered_at": "2025-01-15T12:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "total": 1
  }
}
```

---

## Get Template

```
GET /api/v1/templates/{id}
```

---

## Update Template

```
PUT /api/v1/templates/{id}
```

All fields from create are optional. Only provided fields are updated.

---

## Delete Template

```
DELETE /api/v1/templates/{id}
```

Returns `204 No Content` on success.

---

## Regenerate Token

Generate a new trigger URL. The old URL will immediately stop working.

```
POST /api/v1/templates/{id}/regenerate-token
```

### Response

```json
{
  "message": "Trigger token regenerated successfully.",
  "data": {
    "trigger_token": "clmt_newtoken...",
    "trigger_url": "https://callmelater.io/t/clmt_newtoken..."
  }
}
```

---

## Toggle Active

Enable or disable a template. Inactive templates return 404 when triggered.

```
POST /api/v1/templates/{id}/toggle-active
```

---

## Get Limits

Check your template quota.

```
GET /api/v1/templates/limits
```

### Response

```json
{
  "current": 2,
  "max": 20,
  "remaining": 18,
  "plan": "pro"
}
```

---

## Placeholder Syntax

Use `{{name}}` syntax anywhere in:

- Template name
- Request URL
- Request headers
- Request body
- Gate message
- Coordination keys

### Example: Dynamic Coordination Keys

```json
{
  "default_coordination_keys": [
    "deployment:{{service}}",
    "env:{{env}}"
  ],
  "coordination_config": {
    "on_create": "replace_existing"
  }
}
```

When triggered with `service=api` and `env=prod`, creates coordination keys:
- `deployment:api`
- `env:prod`

This ensures only one pending deployment per service/environment combination.

---

## Rate Limits

Template triggers are rate-limited:

| Limit | Value |
|-------|-------|
| Per token | 60 requests/minute |
| Per IP | 120 requests/minute |
