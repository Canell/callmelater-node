---
sidebar_position: 1
---

# Actions & Scheduling

Everything in CallMeLater is an **action** -- something scheduled to happen in the future. Actions operate in one of two modes: **webhook** or **approval**.

## Webhook mode

Webhook mode is the default. A webhook action delivers an HTTP request to your endpoint at a scheduled time.

```json
{
  "schedule": { "preset": "tomorrow" },
  "request": {
    "method": "POST",
    "url": "https://api.example.com/webhook",
    "headers": { "X-Custom-Header": "value" },
    "body": { "event": "trial_expired", "user_id": 42 }
  }
}
```

Use webhook mode for trial expirations, delayed notifications, scheduled API calls, cleanup tasks, and follow-up triggers.

## Approval mode

An approval action sends an interactive message to one or more recipients and collects their response (confirm, decline, or snooze).

```json
{
  "mode": "approval",
  "schedule": { "wait": "2h" },
  "gate": {
    "message": "Please confirm the deployment",
    "recipients": ["ops@example.com", "+1234567890"],
    "confirmation_mode": "first_response"
  },
  "callback_url": "https://api.example.com/webhooks/response"
}
```

:::info
Approvals do not automatically execute anything. When someone responds, CallMeLater sends the response to your `callback_url`. Your system decides what to do next.
:::

Use approval mode for approval workflows, human confirmations, check-ins, and escalation chains.

## Scheduling

Every action needs a **schedule** that defines when it should fire. There are three ways to set it.

### Presets

Named time references, resolved relative to now (or to a timezone if provided):

| Preset | When |
|--------|------|
| `tomorrow` | Tomorrow at the current time |
| `next_monday` | Next Monday at the current time |
| `next_tuesday` | Next Tuesday at the current time |
| `next_wednesday` | Next Wednesday at the current time |
| `next_thursday` | Next Thursday at the current time |
| `next_friday` | Next Friday at the current time |
| `next_saturday` | Next Saturday at the current time |
| `next_sunday` | Next Sunday at the current time |
| `next_week` | Next Monday at the current time |
| `1h`, `2h`, `4h` | 1, 2, or 4 hours from now |
| `1d`, `3d` | 1 or 3 days from now |
| `1w` | 1 week from now |

```json
{
  "schedule": {
    "preset": "next_monday",
    "timezone": "America/New_York"
  }
}
```

### Relative wait

A duration from now using `schedule.wait`:

```json
{ "schedule": { "wait": "30m" } }
```

| Format | Meaning | Example |
|--------|---------|---------|
| `Nm` | N minutes | `5m` = 5 minutes |
| `Nh` | N hours | `2h` = 2 hours |
| `Nd` | N days | `1d` = 1 day |
| `Nw` | N weeks | `1w` = 1 week |

### Exact time

An ISO 8601 UTC timestamp using `scheduled_for`:

```json
{
  "scheduled_for": "2026-04-01T14:30:00Z"
}
```

### Timezone

Optional. Defaults to **UTC**. Provide a timezone when using presets so that times like `tomorrow` and `next_monday` resolve to the correct local time.

```json
{
  "schedule": {
    "preset": "tomorrow",
    "timezone": "Europe/Paris"
  }
}
```

## Lifecycle states

Actions move through a series of states from creation to completion.

**Webhook mode:**
```
scheduled → resolved → executing → executed
                                  ↘ failed
```

**Approval mode:**
```
scheduled → resolved → executing → awaiting_response → executed
                                                      ↘ failed
                                                      ↘ expired
```

Any non-terminal action can also be `cancelled`.

| State | Description | Next states |
|-------|-------------|-------------|
| `scheduled` | Schedule is being resolved to an exact timestamp | `resolved`, `cancelled` |
| `resolved` | Waiting for the scheduled time to arrive | `executing`, `cancelled` |
| `executing` | HTTP request or reminder is being delivered | `executed`, `failed`, `awaiting_response`, `resolved` (retry) |
| `awaiting_response` | Reminder sent, waiting for a human reply | `executed`, `failed`, `expired`, `cancelled`, `scheduled` (snooze) |
| `executed` | Completed successfully (terminal) | -- |
| `failed` | Failed permanently (terminal) | `resolved` (manual retry) |
| `expired` | Approval timed out without response (terminal) | -- |
| `cancelled` | Cancelled before completion (terminal) | -- |

## Idempotency keys

Use `idempotency_key` to prevent duplicate actions when your system retries requests or a user double-clicks.

```json
{
  "idempotency_key": "trial-end-user-42",
  "schedule": { "wait": "14d" },
  "request": { "url": "https://api.example.com/expire" }
}
```

Key format patterns:

```
trial:user:123
deploy:v2.1
invoice:1234:reminder
weekly-report:2026-W07
```

Keys are scoped per account, up to 255 characters, and case-sensitive.

**Behavior when a matching key already exists:**

| Existing action state | Behavior |
|-----------------------|----------|
| `scheduled` | Returns existing action |
| `resolved` | Returns existing action |
| `awaiting_response` | Returns existing action |
| `executed` | Creates new action |
| `failed` | Creates new action |
| `cancelled` | Creates new action |

Non-terminal states return the existing action to prevent duplicates. Terminal states allow key reuse since the original action is already complete.

## Dedup keys

Dedup keys group related actions together and control how they interact. Unlike idempotency keys (which prevent duplicates of the same request), dedup keys coordinate behavior across different actions that share a logical group.

```json
{
  "dedup_keys": ["deploy:api-service"],
  "coordination": {
    "on_create": "cancel_and_replace"
  },
  "schedule": { "wait": "1h" },
  "request": { "url": "https://ci.example.com/deploy" }
}
```

### `on_create` behaviors

Controls what happens when you create a new action with the same dedup key as an existing non-terminal action.

| Behavior | Description |
|----------|-------------|
| `skip_if_exists` | Return the existing action instead of creating a new one (response includes `meta.skipped: true`) |
| `cancel_and_replace` | Cancel all existing non-terminal actions with matching keys, then create the new action |

### `on_execute` behaviors

Controls what happens at execution time based on other actions sharing the same dedup key. This is a nested object with condition-based logic:

```json
{
  "dedup_keys": ["deploy:api-service"],
  "coordination": {
    "on_execute": {
      "condition": "skip_if_previous_pending",
      "on_condition_not_met": "cancel",
      "reschedule_delay": 300,
      "max_reschedules": 10
    }
  }
}
```

| Field | Description |
|-------|-------------|
| `condition` | `skip_if_previous_pending`, `execute_if_previous_failed`, `execute_if_previous_succeeded`, or `wait_for_previous` |
| `on_condition_not_met` | `cancel`, `reschedule`, or `fail` |
| `reschedule_delay` | Seconds to wait before retrying when rescheduled (used with `reschedule`) |
| `max_reschedules` | Maximum number of reschedule attempts (used with `reschedule`) |

### Format rules

- Alphanumeric characters plus `_`, `:`, `.`, `-`
- No spaces, slashes, or special characters
- Case-sensitive (`Deploy:API` and `deploy:api` are different keys)
- Maximum 10 keys per action
- Scoped to your account

**Valid:** `deploy:production`, `user:42:notifications`, `workflow.onboarding.step-1`

**Invalid:** `key with spaces`, `key/with/slashes`, `key@email.com`
