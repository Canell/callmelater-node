---
sidebar_position: 13
---

# Dedup Keys

List and manage dedup keys used for grouping related actions. Also known as `coordination_keys` for backwards compatibility.

## List Dedup Keys

Get all unique dedup keys used in your account.

```
GET /api/v1/dedup-keys
```

Note: The endpoint `/api/v1/coordination-keys` is also available for backwards compatibility.

### Example

```bash
curl https://api.callmelater.io/v1/dedup-keys \
  -H "Authorization: Bearer sk_live_..."
```

### Response

```json
{
  "keys": [
    "deploy:api-service",
    "deploy:web-app",
    "migration:db-upgrade",
    "user:42:trial",
    "workflow:onboarding"
  ]
}
```

## Filter Actions by Dedup Key

Use the `dedup_key` query parameter on the list actions endpoint:

```
GET /api/v1/actions?dedup_key={key}
```

Note: The query parameter `coordination_key` is also available for backwards compatibility.

### Example

```bash
curl "https://api.callmelater.io/v1/actions?dedup_key=deploy:api-service" \
  -H "Authorization: Bearer sk_live_..."
```

### Response

```json
{
  "data": [
    {
      "id": "action-uuid-1",
      "name": "Deploy API v2.1",
      "status": "executed",
      "dedup_keys": ["deploy:api-service"],
      ...
    },
    {
      "id": "action-uuid-2",
      "name": "Deploy API v2.0 (replaced)",
      "status": "cancelled",
      "dedup_keys": ["deploy:api-service"],
      "replaced_by_action_id": "action-uuid-1",
      ...
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 2
  }
}
```

## Dedup Key Format

Keys must match the pattern: alphanumeric characters plus `_`, `:`, `.`, `-`

**Valid examples:**
- `deploy:production`
- `user:42:notifications`
- `workflow.onboarding.step-1`
- `order_123_fulfillment`

**Invalid examples:**
- `key with spaces` (no spaces allowed)
- `key/with/slashes` (no slashes)
- `key@email.com` (no @ symbol)

## Use Cases

### Deployment Management

```json
{
  "dedup_keys": ["deploy:api-service"],
  "coordination": {
    "on_create": "replace_existing"
  }
}
```

Each new deployment cancels the previous pending one.

### Multi-Step Workflows

```json
// Step 1
{
  "dedup_keys": ["workflow:order-123"],
  "name": "Process payment"
}

// Step 2 (waits for step 1)
{
  "dedup_keys": ["workflow:order-123"],
  "name": "Ship order",
  "coordination": {
    "on_execute": {
      "condition": "wait_for_previous",
      "on_condition_not_met": "reschedule"
    }
  }
}
```

### Conditional Execution

```json
// Only run cleanup if processing failed
{
  "dedup_keys": ["job:data-import"],
  "name": "Cleanup failed import",
  "coordination": {
    "on_execute": {
      "condition": "execute_if_previous_failed"
    }
  }
}
```

### Deduplication

```json
{
  "dedup_keys": ["notification:user-42-weekly"],
  "coordination": {
    "on_create": "skip_if_exists"
  }
}
```

If a notification is already scheduled, returns the existing one instead of creating a duplicate.

## Related Actions

When viewing an action with dedup keys, the response includes `related_actions`:

```json
{
  "data": {
    "id": "current-action",
    "dedup_keys": ["deploy:api"],
    "related_actions": [
      {
        "id": "previous-action-1",
        "name": "Deploy v1.9",
        "status": "executed",
        "created_at": "2025-01-01T10:00:00Z"
      },
      {
        "id": "previous-action-2",
        "name": "Deploy v1.8",
        "status": "cancelled",
        "created_at": "2024-12-15T10:00:00Z"
      }
    ]
  }
}
```

## Notes

- Keys are case-sensitive (`Deploy:API` ≠ `deploy:api`)
- Maximum 10 keys per action
- Keys are scoped to your account (other accounts can use the same keys)
- Deleted/expired actions still appear in related_actions for audit purposes
- The API accepts both `dedup_keys` and `coordination_keys` for backwards compatibility
