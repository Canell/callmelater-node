---
sidebar_position: 4
---

# Reminders

Reminders let you get human confirmation before proceeding with an action.

## How Reminders Work

1. You create a reminder action with recipients
2. At the scheduled time, CallMeLater sends the reminder (email/SMS)
3. Recipients see a simple interface: **Confirm**, **Decline**, or **Snooze**
4. Their response triggers your webhook or updates the action state

## Creating a Reminder

```json
{
  "type": "reminder",
  "intent": {
    "preset": "tomorrow"
  },
  "reminder_message": "Please confirm the production deployment",
  "recipients": [
    "ops@example.com",
    "+1234567890"
  ],
  "confirmation_mode": "any",
  "max_snoozes": 3,
  "snooze_duration": "1h",
  "expires_after": "24h"
}
```

## Recipient Channels

CallMeLater automatically detects the channel:

| Format | Channel |
|--------|---------|
| `user@example.com` | Email |
| `+1234567890` | SMS |

You can mix channels in the same reminder.

## Confirmation Modes

### `any` (Default)

The reminder is resolved when **any** recipient responds.

Use for: General notifications where one confirmation is enough.

### `all`

The reminder requires **all** recipients to confirm.

Use for: Critical approvals requiring multiple sign-offs.

## Response Options

Recipients can:

| Response | Effect |
|----------|--------|
| **Confirm** | Action marked as `executed`, triggers success webhook |
| **Decline** | Action marked as `failed`, triggers failure webhook |
| **Snooze** | Reminder rescheduled, counter decremented |

## Snoozing

Configure snooze behavior:

```json
{
  "max_snoozes": 3,
  "snooze_duration": "1h"
}
```

- `max_snoozes`: How many times recipients can snooze (default: 3)
- `snooze_duration`: How long until the next reminder (default: 1 hour)

When snooze limit is reached, the snooze button is hidden.

## Expiration

Set when an unanswered reminder should expire:

```json
{
  "expires_after": "24h"
}
```

When a reminder expires without response:
- Status changes to `failed`
- Escalation contacts are notified (if configured)

## Escalation

Configure escalation for critical reminders:

```json
{
  "escalation_contacts": [
    "manager@example.com"
  ],
  "escalate_after": "4h"
}
```

If no response within 4 hours, escalation contacts receive the reminder.

## One-Click Responses

Recipients don't need to log in. Each reminder email/SMS includes unique response links:

```
https://callmelater.io/respond?token=abc123&response=confirm
```

Clicking the link immediately records the response.

## Tracking Responses

View the response timeline:

```json
{
  "reminder_events": [
    {
      "event_type": "sent",
      "recipient": "ops@example.com",
      "channel": "email",
      "occurred_at": "2025-01-05T09:00:00Z"
    },
    {
      "event_type": "response",
      "recipient": "ops@example.com",
      "response": "confirm",
      "occurred_at": "2025-01-05T09:15:00Z"
    }
  ]
}
```

## Use Cases

- **Deployment approvals**: Confirm before pushing to production
- **Expense approvals**: Manager sign-off on purchases
- **Safety checks**: Verify someone completed a critical task
- **On-call escalation**: Ping the team until someone responds
