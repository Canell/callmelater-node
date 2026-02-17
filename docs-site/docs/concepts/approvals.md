---
sidebar_position: 2
---

# Approvals & Reminders

Approval actions send interactive messages to humans and collect their responses. When someone responds, CallMeLater notifies your system -- your code decides what happens next.

## How it works

1. **Create action** -- You create an approval action with a message, recipients, and a `callback_url`.
2. **CallMeLater sends reminder** -- At the scheduled time, CallMeLater delivers the reminder via email or SMS.
3. **Recipient responds** -- The recipient clicks Confirm, Decline, or Snooze directly from the message (no login required).
4. **CallMeLater calls your callback** -- CallMeLater sends the response details to your `callback_url`. Your system decides what to do.

:::info
Approvals don't automatically execute anything. When someone responds, CallMeLater sends the response to your callback URL. Your system decides what to do next.
:::

## Recipients

Recipients are auto-detected by format:

| Format | Channel | Example |
|--------|---------|---------|
| Email address | Email | `ops@example.com` |
| E.164 phone number | SMS | `+1234567890` |
| Channel reference | Channel | `channel:uuid` |

You can mix formats in the same action:

```json
{
  "gate": {
    "recipients": ["ops@example.com", "+1234567890"]
  }
}
```

## Confirmation modes

| Mode | Behavior |
|------|----------|
| `first_response` (default) | Completes as soon as any single recipient responds |
| `all_required` | Waits until every recipient has responded |

Use `first_response` for general notifications where one confirmation is enough. Use `all_required` for critical approvals that need multiple sign-offs.

```json
{
  "gate": {
    "confirmation_mode": "all_required",
    "recipients": ["cto@example.com", "security@example.com"]
  }
}
```

## Response options

| Action | Description |
|--------|-------------|
| **Confirm** | Marks the action as `executed` and sends response details to your callback URL |
| **Decline** | Marks the action as `failed` and sends response details to your callback URL |
| **Snooze** | Reschedules the reminder to be sent again after a configurable period |

## Snooze

Control how many times recipients can snooze with `max_snoozes`:

```json
{
  "gate": {
    "max_snoozes": 3
  }
}
```

- Default: `5`
- Set to `0` to disable snoozing entirely (hides the snooze button)
- When the snooze limit is reached, the snooze option disappears from subsequent reminders

## Token expiry

Response links expire after a configurable duration. Once expired, the action moves to `expired` status and the links stop working.

```json
{
  "gate": {
    "timeout": "7d"
  }
}
```

- Default: `7d` (7 days)
- Configurable per action via `gate.timeout` (e.g., `"3d"`, `"12h"`, `"7d"`)

## Escalation

If nobody responds within a time window, CallMeLater can automatically notify escalation contacts.

```json
{
  "gate": {
    "message": "Approve the production deployment",
    "recipients": ["team@example.com"],
    "escalation_contacts": ["manager@example.com"],
    "escalation_after_hours": 4
  }
}
```

- `escalation_contacts` -- array of email addresses or phone numbers to notify if no response is received
- `escalation_after_hours` -- hours to wait before escalating (minimum 0.5)
- If a contact has no prefix (like `channel:`), email is assumed automatically

Escalation is about getting human attention when the original recipients are unresponsive. It is separate from retries, which handle technical delivery failures.

## Team members

Instead of using raw email addresses and phone numbers in your API calls, you can create contacts in **Settings > Team Members** and reference them by member ID:

```json
{
  "gate": {
    "recipients": ["member-uuid-1", "member-uuid-2"]
  }
}
```

CallMeLater looks up each member's contact details and sends to the appropriate channel. Responses are tracked with team member attribution.
