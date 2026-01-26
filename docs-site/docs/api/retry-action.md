---
sidebar_position: 6
---

# Retry Action

Manually retry a failed action. This creates a new execution cycle and resets the attempt counter.

```
POST /api/v1/actions/{id}/retry
```

## Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | string | Action UUID |

## Requirements

- Action must be in `failed` status
- Action must have an HTTP request configured
- Manual retry is available (plan-dependent limits may apply)

## Example

```bash
curl -X POST https://api.callmelater.io/v1/actions/01234567-89ab-cdef-0123-456789abcdef/retry \
  -H "Authorization: Bearer sk_live_..."
```

## Response

### Success (200 OK)

```json
{
  "message": "Action retry initiated",
  "execution_cycle_id": "cycle-uuid-new",
  "action": {
    "id": "01234567-89ab-cdef-0123-456789abcdef",
    "name": "Failed webhook",
    "mode": "immediate",
    "status": "resolved",
    "manual_retry_count": 1,
    "last_manual_retry_at": "2025-01-08T10:00:00Z",
    "attempt_count": 0,
    "execution_cycles": [
      {
        "id": "cycle-uuid-1",
        "cycle_number": 1,
        "trigger": "scheduled",
        "outcome": "failed"
      },
      {
        "id": "cycle-uuid-new",
        "cycle_number": 2,
        "trigger": "manual_retry",
        "triggered_by": {
          "id": "user-uuid",
          "email": "user@example.com"
        },
        "started_at": "2025-01-08T10:00:00Z",
        "outcome": null
      }
    ]
  }
}
```

### Retry Not Allowed (422)

```json
{
  "message": "Retry not allowed",
  "reasons": [
    "Action is not in failed status",
    "Maximum manual retries reached (3)"
  ]
}
```

### Not Found (404)

```json
{
  "message": "Action not found"
}
```

## Notes

- Manual retry resets `attempt_count` to 0, allowing the full retry sequence to run again
- Each manual retry is tracked in `manual_retry_count` and `last_manual_retry_at`
- A new execution cycle is created with `trigger: "manual_retry"`
- The action transitions from `failed` → `resolved` → `executing` through the normal flow
- Plan limits may restrict the number of manual retries available
