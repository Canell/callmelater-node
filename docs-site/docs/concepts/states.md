---
sidebar_position: 2
---

# Action States

Every action moves through a lifecycle of states. Understanding these states helps you track actions and handle edge cases.

## State Diagram

```
┌─────────────────────┐
│      scheduled      │  Schedule being resolved
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐     ┌───────────┐
│      resolved       │◄────│  (retry)  │
└──────────┬──────────┘     └───────────┘
           │                      ▲
           ▼                      │
┌─────────────────────┐           │
│     executing       │───────────┘
└──────────┬──────────┘
           │
    ┌──────┴──────────────┐
    │                     │
    ▼                     ▼
┌────────┐      ┌──────────────────┐
│executed│      │awaiting_response │  (approval mode only)
└────────┘      └────────┬─────────┘
                         │
              ┌──────────┼──────────┬──────────┐
              ▼          ▼          ▼          ▼
         ┌────────┐ ┌────────┐ ┌───────┐ ┌─────────┐
         │executed│ │ failed │ │expired│ │cancelled│
         └────────┘ └────────┘ └───────┘ └─────────┘

Terminal states: executed, failed, expired, cancelled

Note: "scheduled" is also known as "pending_resolution" for backwards compatibility.
```

## States Explained

### `scheduled`

The action was created but the scheduling hasn't been fully resolved yet. Also known as `pending_resolution` for backwards compatibility.

This typically happens immediately after creation and transitions to `resolved` within milliseconds. The system is calculating the exact execution time based on presets, waits, or timezones.

**Can transition to:** `resolved`, `cancelled`

### `resolved`

The action is scheduled and waiting for its execution time.

The action has a concrete `scheduled_for` timestamp (also known as `execute_at`) and will be picked up by the dispatcher when due.

**Can transition to:** `executing`, `cancelled`

### `executing`

The action is currently being processed by the delivery system.

This is a transient state that prevents duplicate execution. The action is locked while being delivered.

**For webhook mode:** HTTP request is being made.

**For approval mode:** Gate notification is being sent to recipients.

**Can transition to:** `executed`, `failed`, `awaiting_response`, `resolved` (for retry)

### `awaiting_response`

*Approval mode only.* The gate notification has been sent and we're waiting for a human response.

Recipients can:
- **Confirm** → transitions to `executed` (or `resolved` if HTTP request configured)
- **Decline** → transitions to `failed`
- **Snooze** → transitions to `scheduled` (rescheduled)

**Can transition to:** `executed`, `failed`, `expired`, `cancelled`, `resolved`, `scheduled`

### `executed`

The action completed successfully. **Terminal state.**

**For webhook mode:** The HTTP request received a 2xx response.

**For approval mode:** A recipient confirmed, and any configured HTTP request completed successfully.

### `failed`

The action failed permanently. **Terminal state.**

**For webhook mode:** All retry attempts exhausted without success, or a non-retryable error occurred (4xx response, security violation, etc.).

**For approval mode:** Recipient declined, or on_timeout was set to "cancel" and timeout occurred.

Failed actions can be manually retried via the API if eligible.

### `expired`

*Approval mode only.* The reminder expired without a response. **Terminal state.**

This happens when the token expires and `on_timeout` is set to `expire` (the default).

### `cancelled`

The action was cancelled before completion. **Terminal state.**

Cancellation can happen:
- Manually via API call
- Automatically via coordination rules (e.g., `replace_existing`, `skip_if_previous_pending`)
- When `on_timeout` is set to `cancel`

## State Transitions Summary

| From | To | Trigger |
|------|----|----|
| `scheduled` | `resolved` | Schedule resolved successfully |
| `scheduled` | `cancelled` | Manual cancellation |
| `resolved` | `executing` | Dispatcher picks up action |
| `resolved` | `cancelled` | Manual cancellation or coordination |
| `executing` | `executed` | HTTP success (webhook mode) |
| `executing` | `failed` | Non-retryable failure or max retries |
| `executing` | `resolved` | Retryable failure, scheduled for retry |
| `executing` | `awaiting_response` | Gate sent (approval mode) |
| `awaiting_response` | `executed` | Recipient confirmed |
| `awaiting_response` | `failed` | Recipient declined |
| `awaiting_response` | `expired` | Token expired (on_timeout: expire) |
| `awaiting_response` | `cancelled` | Manual cancel or on_timeout: cancel |
| `awaiting_response` | `scheduled` | Recipient snoozed |
| `awaiting_response` | `resolved` | Gate passed, HTTP request queued |
| `failed` | `resolved` | Manual retry initiated |

Note: `scheduled` is also known as `pending_resolution` for backwards compatibility.

## Querying by State

Filter actions by state using the API:

```bash
# Get all scheduled actions (waiting for execution)
curl "https://api.callmelater.io/v1/actions?status=resolved"

# Get failed actions
curl "https://api.callmelater.io/v1/actions?status=failed"

# Get actions awaiting human response
curl "https://api.callmelater.io/v1/actions?status=awaiting_response"
```

## State Timestamps

Actions include timestamps for tracking:

```json
{
  "id": "uuid",
  "status": "executed",
  "scheduled_for": "2025-01-05T09:00:00Z",
  "executed_at": "2025-01-05T09:00:02Z",
  "gate_passed_at": "2025-01-05T08:55:00Z",
  "created_at": "2025-01-04T10:00:00Z",
  "updated_at": "2025-01-05T09:00:02Z"
}
```

| Field | Description |
|-------|-------------|
| `scheduled_for` | Scheduled execution time. Also known as `execute_at` for backwards compatibility. |
| `executed_at` | When action completed (executed status) |
| `gate_passed_at` | When gate was approved (approval mode) |
| `token_expires_at` | When response tokens expire (approval mode) |
| `next_retry_at` | Scheduled retry time (if retrying) |
| `last_manual_retry_at` | Last manual retry timestamp |
