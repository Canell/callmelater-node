---
sidebar_position: 2
---

# Action States

Every action moves through a lifecycle of states.

## State Diagram

```
┌─────────────────────┐
│  pending_resolution │  Intent being resolved
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│      resolved       │  Waiting for scheduled time
└──────────┬──────────┘
           │
           ▼
    ┌──────┴──────┐
    │             │
    ▼             ▼
┌────────┐  ┌──────────────────┐
│executed│  │awaiting_response │  (reminders only)
└────────┘  └────────┬─────────┘
                     │
              ┌──────┼──────┐
              ▼      ▼      ▼
         ┌────────┐ ┌──┐ ┌────────┐
         │executed│ │  │ │ failed │
         └────────┘ │  │ └────────┘
                    │  │
                    ▼  ▼
              ┌───────────┐
              │ cancelled │
              └───────────┘
```

## States Explained

### `pending_resolution`

The action was created but the intent hasn't been resolved yet.

This typically happens immediately after creation and transitions to `resolved` within milliseconds.

### `resolved`

The action is scheduled and waiting for its execution time.

**For HTTP actions:** Will transition to `executed` or `failed` after delivery.

**For reminders:** Will transition to `awaiting_response` when the reminder is sent.

### `awaiting_response`

*Reminders only.* The reminder has been sent and we're waiting for a response.

Can transition to:
- `executed` — recipient confirmed
- `failed` — recipient declined or reminder expired
- `resolved` — recipient snoozed (rescheduled)

### `executed`

The action completed successfully.

**For HTTP actions:** The webhook received a 2xx response.

**For reminders:** A recipient confirmed.

This is a terminal state.

### `failed`

The action failed permanently.

**For HTTP actions:** All retry attempts exhausted without success.

**For reminders:** Recipient declined, or no response before expiry.

This is a terminal state.

### `cancelled`

The action was cancelled before execution.

This is a terminal state.

## Querying by State

Filter actions by state using the API:

```bash
# Get all pending actions
GET /api/v1/actions?status=resolved

# Get failed actions
GET /api/v1/actions?status=failed
```

## State Timestamps

Each action includes timestamps for state transitions:

```json
{
  "id": "uuid",
  "resolution_status": "executed",
  "execute_at_utc": "2025-01-05T09:00:00Z",
  "last_attempt_at": "2025-01-05T09:00:02Z",
  "resolved_at": "2025-01-04T10:00:00Z",
  "created_at": "2025-01-04T10:00:00Z"
}
```
