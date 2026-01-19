---
sidebar_position: 4
---

# Reminders

Reminders let you collect human responses (confirm, decline, or snooze). When someone responds, CallMeLater notifies your system via a callback URL — your system then decides what action to take.

## How Reminders Work

1. You create a reminder action with recipients
2. At the scheduled time, CallMeLater sends the reminder (email/SMS)
3. Recipients see a simple interface: **Confirm**, **Decline**, or **Snooze**
4. Their response triggers your callback URL with response details

:::info Reminders vs HTTP Actions
**HTTP actions** execute your webhook directly at the scheduled time.

**Reminders** collect a human response, then call your callback URL with the response details. Your system receives the response and decides what to do next — CallMeLater doesn't automatically execute any business logic.
:::

## Creating a Reminder

```json
{
  "type": "reminder",
  "intent": {
    "preset": "tomorrow"
  },
  "message": "Please confirm the production deployment",
  "escalation_rules": {
    "recipients": ["ops@example.com", "+1234567890"],
    "channels": ["email", "sms"],
    "token_expiry_days": 1
  },
  "confirmation_mode": "first_response",
  "max_snoozes": 3
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

### `first_response` (Default)

The reminder is resolved when **any** recipient responds.

Use for: General notifications where one confirmation is enough.

### `all_required`

The reminder requires **all** recipients to confirm.

Use for: Critical approvals requiring multiple sign-offs.

## Response Options

Recipients can:

| Response | Effect |
|----------|--------|
| **Confirm** | Action marked as `executed`, callback URL receives response details |
| **Decline** | Action marked as `failed`, callback URL receives response details |
| **Snooze** | Reminder rescheduled, counter decremented |

## Snoozing

Configure snooze behavior:

```json
{
  "max_snoozes": 5
}
```

- `max_snoozes`: How many times recipients can snooze (default: 5)

When snooze limit is reached, the snooze button is hidden.

## Expiration

Set when response links should expire:

```json
{
  "escalation_rules": {
    "recipients": ["user@example.com"],
    "token_expiry_days": 7
  }
}
```

- `token_expiry_days`: Days until response links expire (default: 7, max: 30)

When a reminder expires without response:
- Status changes to `expired`
- Response links no longer work

## Escalation

Configure escalation for critical reminders:

```json
{
  "escalation_rules": {
    "recipients": ["team@example.com"],
    "escalation_contacts": ["manager@example.com"],
    "escalate_after_hours": 4
  }
}
```

If no response within 4 hours, escalation contacts receive the reminder.

:::tip Escalation vs. Retry
**Escalation** and **retry** are different concepts:

- **Retry** = resend to the *same* recipient after a delivery failure
- **Escalation** = notify *additional* humans when there's no response

Escalation is about getting human attention, not handling technical failures.
:::

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
