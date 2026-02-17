---
sidebar_position: 3
---

# Chains

Chains are multi-step workflows that execute actions sequentially. Each step can be an HTTP call, a human approval gate, or a timed wait. Data flows between steps through variable interpolation.

## Create Chain

```
POST /chains
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Chain name (max 255 chars) |
| `steps` | array | Yes | Step definitions (min 2, max 20) |
| `input` | object | No | Input variables available for interpolation in all steps |
| `error_handling` | string | No | `fail_chain` (default) or `skip_step` |

### Step Types

Every step requires a `name`, a `type`, and optionally a `condition`.

**`http_call` step**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Step name |
| `url` | string | Yes | Request URL |
| `method` | string | No | HTTP method (default: `POST`) |
| `headers` | object | No | Request headers |
| `body` | object | No | Request body |
| `max_attempts` | integer | No | Max delivery attempts (default: 5) |
| `retry_strategy` | string | No | `exponential` (default) or `linear` |
| `condition` | string | No | Expression that must evaluate to true for the step to run |

**`gated` step**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Step name |
| `gate.message` | string | Yes | Approval request message |
| `gate.recipients` | array | Yes | Email addresses, phone numbers, or contact IDs |
| `gate.timeout` | string | No | Response timeout (e.g., `4h`, `7d`). Default: `7d`. |
| `gate.on_timeout` | string | No | `cancel` (default), `expire`, or `approve` |
| `gate.confirmation_mode` | string | No | `first_response` (default) or `all_required` |
| `gate.max_snoozes` | integer | No | Max snooze count (default: 5) |
| `condition` | string | No | Expression that must evaluate to true for the step to run |

**`delay` step**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Step name |
| `delay` | string | Yes | How long to pause (e.g., `5m`, `1h`, `2d`) |
| `condition` | string | No | Expression that must evaluate to true for the step to run |

> **Note:** Responses return `webhook`, `approval`, `wait` as aliases for `http_call`, `gated`, `delay` respectively.

### Variable Interpolation

Steps can reference input data and results from earlier steps:

- `{{input.field}}` -- Access values from the chain's `input` object
- `{{steps.N.response.field}}` -- Access the JSON response body of step N (zero-indexed)
- `{{steps.N.status}}` -- Access the outcome status of step N

### Condition Operators

| Operator | Description |
|----------|-------------|
| `==` | Equal |
| `!=` | Not equal |
| `>` | Greater than |
| `<` | Less than |
| `>=` | Greater than or equal |
| `<=` | Less than or equal |
| `contains` | String contains substring |
| `not_contains` | String does not contain substring |
| `starts_with` | String starts with prefix |
| `ends_with` | String ends with suffix |

Example condition: `{{steps.1.status}} == 'confirmed'`

### Example

```bash
curl -X POST https://api.callmelater.io/v1/chains \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Expense Approval Workflow",
    "steps": [
      {
        "name": "Submit expense",
        "type": "http_call",
        "url": "https://api.example.com/expenses",
        "method": "POST",
        "body": {
          "amount": "{{input.amount}}",
          "description": "{{input.description}}"
        }
      },
      {
        "name": "Manager approval",
        "type": "gated",
        "gate": {
          "message": "Approve expense of ${{input.amount}} for {{input.description}}?",
          "recipients": ["manager@example.com"],
          "timeout": "4h",
          "on_timeout": "cancel"
        }
      },
      {
        "name": "Process payment",
        "type": "http_call",
        "url": "https://api.example.com/payments",
        "method": "POST",
        "body": {
          "expense_id": "{{steps.0.response.id}}"
        },
        "condition": "{{steps.1.status}} == '\''confirmed'\''"
      }
    ],
    "input": {
      "amount": 150.00,
      "description": "Team lunch"
    }
  }'
```

---

## List Chains

```
GET /chains
```

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | -- | Filter by status: `pending`, `running`, `completed`, `failed`, `cancelled` |
| `per_page` | integer | 15 | Results per page (max 100) |
| `page` | integer | 1 | Page number |

### Response

```json
{
  "data": [
    {
      "id": "01chain789-...",
      "name": "Expense Approval Workflow",
      "status": "running",
      "current_step": 1,
      "created_at": "2026-01-15T10:00:00Z"
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

## Get Chain

```
GET /chains/{id}
```

Returns the chain with its full step array, including each step's current status, response data (in `context`), `started_at`, and `completed_at` timestamps.

### Response

```json
{
  "data": {
    "id": "01chain789-...",
    "name": "Expense Approval Workflow",
    "status": "running",
    "current_step": 1,
    "steps": [
      {
        "name": "Submit expense",
        "type": "http_call",
        "status": "completed",
        "started_at": "2026-01-15T10:00:05Z",
        "completed_at": "2026-01-15T10:00:06Z"
      },
      {
        "name": "Manager approval",
        "type": "approval",
        "status": "running",
        "started_at": "2026-01-15T10:00:07Z",
        "completed_at": null
      },
      {
        "name": "Process payment",
        "type": "http_call",
        "status": "pending",
        "started_at": null,
        "completed_at": null
      }
    ],
    "context": {
      "steps": {
        "0": { "status": "completed", "response": { "id": "exp_123" } }
      }
    },
    "error_handling": "fail_chain",
    "created_at": "2026-01-15T10:00:00Z"
  }
}
```

---

## Cancel Chain

```
DELETE /chains/{id}
```

Only chains in `pending` or `running` status can be cancelled. Cancelling a chain also cancels any currently pending steps.

### Response

```json
{
  "data": {
    "id": "01chain789-...",
    "status": "cancelled"
  }
}
```

---

## Statuses

**Chain statuses:** `pending`, `running`, `completed`, `failed`, `cancelled`

**Step statuses:** `pending`, `running`, `completed`, `failed`, `skipped`, `cancelled`
