---
sidebar_position: 8
---

# Chains API

Chains are multi-step workflows that execute actions sequentially with data passing between steps. Each step can be a webhook, a human approval gate, or a wait.

## Why Chains?

- **Sequential workflows** - Execute steps in order, waiting for each to complete
- **Human checkpoints** - Insert approval gates between automated steps
- **Data passing** - Use responses from previous steps in later steps
- **Error handling** - Choose to fail the chain or skip failed steps

---

## Create Chain

```
POST /api/v1/chains
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Chain name (max 255 chars) |
| `steps` | array | Yes | Step definitions (min 2, max 20) |
| `input` | object | No | Input variables for interpolation |
| `error_handling` | string | No | `fail_chain` (default) or `skip_step` |

### Step Definition

Each step requires:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Step name |
| `type` | string | Yes | `webhook`, `approval`, or `wait`. Also known as `http_call`, `gated`, or `delay` for backwards compatibility. |
| `condition` | string | No | Expression that must be true to execute |

**Webhook Step:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `url` | string | Yes | Request URL |
| `method` | string | No | HTTP method (default: POST) |
| `headers` | object | No | Request headers |
| `body` | object | No | Request body |

**Approval Step:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `gate.message` | string | Yes | Approval request message |
| `gate.recipients` | array | No | Recipient emails/phones |
| `gate.channels` | array | No | `email`, `sms`, `teams`, `slack` |

**Wait Step:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `wait` | string | Yes | Duration (e.g., `5m`, `1h`, `2d`). Also known as `delay` for backwards compatibility. |

### Example: Approval Workflow

```bash
curl -X POST https://callmelater.io/api/v1/chains \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Expense Approval",
    "steps": [
      {
        "name": "Submit Expense",
        "type": "webhook",
        "url": "https://api.example.com/expenses",
        "method": "POST",
        "body": {
          "amount": "{{input.amount}}",
          "description": "{{input.description}}"
        }
      },
      {
        "name": "Manager Approval",
        "type": "approval",
        "gate": {
          "message": "Approve expense of ${{input.amount}} for {{input.description}}?",
          "recipients": ["manager@example.com"],
          "channels": ["email", "slack"]
        }
      },
      {
        "name": "Process Payment",
        "type": "webhook",
        "url": "https://api.example.com/payments",
        "method": "POST",
        "body": {
          "expense_id": "{{steps.0.response.id}}"
        }
      }
    ],
    "input": {
      "amount": 150.00,
      "description": "Team lunch"
    }
  }'
```

### Response

```json
{
  "data": {
    "id": "01chain789-...",
    "name": "Expense Approval",
    "status": "pending",
    "current_step": 0,
    "steps": [
      {"name": "Submit Expense", "type": "webhook", "status": "pending"},
      {"name": "Manager Approval", "type": "approval", "status": "pending"},
      {"name": "Process Payment", "type": "webhook", "status": "pending"}
    ],
    "error_handling": "fail_chain",
    "created_at": "2025-01-15T10:00:00Z"
  }
}
```

---

## List Chains

```
GET /api/v1/chains
```

### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by status: `pending`, `running`, `completed`, `failed`, `cancelled` |
| `page` | integer | Page number |

### Response

```json
{
  "data": [
    {
      "id": "01chain789-...",
      "name": "Expense Approval",
      "status": "running",
      "current_step": 1,
      "created_at": "2025-01-15T10:00:00Z"
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
GET /api/v1/chains/{id}
```

### Response

```json
{
  "data": {
    "id": "01chain789-...",
    "name": "Expense Approval",
    "status": "running",
    "current_step": 1,
    "steps": [
      {
        "name": "Submit Expense",
        "type": "webhook",
        "status": "executed",
        "response": {"id": "exp_123", "status": "submitted"}
      },
      {
        "name": "Manager Approval",
        "type": "approval",
        "status": "awaiting_response"
      },
      {
        "name": "Process Payment",
        "type": "webhook",
        "status": "pending"
      }
    ],
    "input": {"amount": 150.00, "description": "Team lunch"},
    "context": {
      "steps": {
        "0": {"status": "executed", "response": {"id": "exp_123"}}
      }
    },
    "error_handling": "fail_chain",
    "started_at": "2025-01-15T10:00:05Z",
    "created_at": "2025-01-15T10:00:00Z"
  }
}
```

---

## Cancel Chain

Cancel a running or pending chain. Cancels any pending actions.

```
DELETE /api/v1/chains/{id}
```

### Response

```json
{
  "data": {
    "id": "01chain789-...",
    "status": "cancelled"
  }
}
```

### Errors

| Status | Error | Description |
|--------|-------|-------------|
| 400 | `invalid_status` | Cannot cancel completed/failed chains |
| 404 | `not_found` | Chain not found |

---

## Variable Interpolation

Chains support variable interpolation in step URLs, headers, bodies, and gate messages.

### Input Variables

Access input values with `{{input.field}}`:

```json
{
  "url": "https://api.example.com/users/{{input.user_id}}",
  "body": {
    "name": "{{input.name}}",
    "email": "{{input.email}}"
  }
}
```

### Previous Step Responses

Access responses from earlier steps with `{{steps.N.response.field}}`:

```json
{
  "body": {
    "order_id": "{{steps.0.response.id}}",
    "total": "{{steps.0.response.total}}"
  }
}
```

### Step Status

Access the status of earlier steps with `{{steps.N.status}}`:

- `executed` - Webhook step completed successfully
- `failed` - Webhook step failed
- `confirmed` - Approval step was approved
- `declined` - Approval step was rejected
- `skipped` - Step skipped due to condition

---

## Step Conditions

Make steps conditional based on previous step results:

```json
{
  "name": "Send Welcome Email",
  "type": "http_call",
  "url": "https://api.example.com/welcome",
  "condition": "{{steps.1.status}} == 'confirmed'"
}
```

### Supported Operators

- `==` Equal
- `!=` Not equal
- `>` Greater than
- `<` Less than
- `>=` Greater than or equal
- `<=` Less than or equal
- `&&` And
- `||` Or

### Examples

```javascript
// Only if previous step succeeded
"{{steps.0.status}} == 'executed'"

// Only if approval was granted
"{{steps.1.status}} == 'confirmed'"

// Only for high-value orders
"{{steps.0.response.total}} > 1000"

// Complex condition
"{{steps.0.status}} == 'executed' && {{steps.1.status}} == 'confirmed'"
```

---

## Error Handling

### `fail_chain` (default)

If any step fails, the entire chain is marked as failed. No further steps execute.

### `skip_step`

If a step fails, it's marked as skipped and the chain continues to the next step.

```json
{
  "error_handling": "skip_step"
}
```

---

## Chain Statuses

| Status | Description |
|--------|-------------|
| `pending` | Chain created, not yet started |
| `running` | Chain is executing steps |
| `completed` | All steps completed successfully |
| `failed` | Chain failed (step failure with `fail_chain`) |
| `cancelled` | Chain was manually cancelled |

---

## Step Statuses

| Status | Description |
|--------|-------------|
| `pending` | Step hasn't started |
| `executing` | Step is currently running |
| `executed` | Webhook step completed successfully |
| `failed` | Step failed |
| `confirmed` | Approval step was approved |
| `declined` | Approval step was rejected |
| `skipped` | Step skipped (condition not met or error with `skip_step`) |
