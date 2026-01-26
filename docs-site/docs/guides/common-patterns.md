---
sidebar_position: 1
---

# Common Patterns

Ready-to-use recipes for common use cases. Copy, adapt, and ship.

## Trial Expiration

Automatically handle what happens when a free trial ends.

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "idempotency_key": "trial-end-user-42",
    "intent": {
      "delay": "14d"
    },
    "request": {
      "method": "POST",
      "url": "https://api.yourapp.com/webhooks/trial-expired",
      "body": {
        "user_id": 42,
        "action": "downgrade_to_free"
      }
    },
    "max_attempts": 5
  }'
```

**Key points:**
- Use `idempotency_key` with the user ID so you can cancel if they upgrade
- Set `max_attempts` high — this webhook matters
- Your webhook should be idempotent (handle duplicate calls gracefully)

**Cancel if they upgrade:**
```bash
curl -X DELETE https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{"idempotency_key": "trial-end-user-42"}'
```

---

## Human Gate Before Deploy

Require approval before a deployment proceeds.

```javascript
async function requestDeployApproval(version, environment) {
  const action = await fetch('https://api.callmelater.io/v1/actions', {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer sk_live_...',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      mode: 'gated',
      idempotency_key: `deploy-${version}-${environment}`,
      intent: { delay: '0m' }, // Send immediately
      gate: {
        message: `Approve deployment of ${version} to ${environment}?`,
        recipients: ['tech-lead@company.com'],
        escalation_contacts: ['cto@company.com'],
        escalate_after_hours: 1,
        token_expiry_days: 1,
        confirmation_mode: environment === 'production'
          ? 'all_required'
          : 'first_response'
      }
    })
  });

  return action.json();
}
```

**Key points:**
- `delay: '0m'` sends the reminder immediately
- Use `all_required` for production to get multiple sign-offs
- Set short `token_expiry_days` for time-sensitive deployments
- Escalate to management if no response

---

## Delayed Cleanup

Delete temporary data after a retention period.

```json
{
  "idempotency_key": "cleanup-export-abc123",
  "intent": {
    "delay": "7d"
  },
  "request": {
    "method": "DELETE",
    "url": "https://api.yourapp.com/exports/abc123",
    "headers": {
      "X-Internal-Key": "cleanup-service"
    }
  }
}
```

**Key points:**
- Schedule cleanup when you create the temporary resource
- Use the resource ID in the idempotency key
- If the user deletes it manually, the cleanup webhook becomes a no-op

---

## Escalation-Only Reminder

Ping a backup person only if the primary doesn't respond.

```json
{
  "mode": "gated",
  "intent": {
    "delay": "0m"
  },
  "gate": {
    "message": "Server CPU critical on prod-web-01. Please investigate.",
    "recipients": ["oncall@company.com"],
    "escalation_contacts": ["oncall-backup@company.com", "+1234567890"],
    "escalate_after_hours": 0.5,
    "channels": ["email", "sms"],
    "max_snoozes": 0
  }
}
```

**Key points:**
- `escalate_after_hours: 0.5` = escalate after 30 minutes
- `max_snoozes: 0` forces immediate action (no snooze button)
- Mix email and SMS for urgent alerts
- Add phone number for SMS escalation

---

## Scheduled Report Generation

Trigger weekly report generation.

```json
{
  "idempotency_key": "weekly-report-2025-w02",
  "intent": {
    "preset": "next_monday",
    "timezone": "America/New_York"
  },
  "request": {
    "method": "POST",
    "url": "https://api.yourapp.com/reports/generate",
    "body": {
      "type": "weekly_summary",
      "week": "2025-W02"
    }
  }
}
```

**Key points:**
- Include the week in `idempotency_key` to prevent duplicates
- Specify `timezone` for consistent "Monday morning" across DST changes
- Your report endpoint should create the *next* week's action when it runs

---

## Follow-Up Sequence

Send a series of onboarding emails.

```javascript
// When user signs up, schedule the sequence
async function scheduleOnboarding(userId, email) {
  const sequence = [
    { delay: '1d', template: 'welcome' },
    { delay: '3d', template: 'getting_started' },
    { delay: '7d', template: 'pro_tips' },
  ];

  for (const step of sequence) {
    await fetch('https://api.callmelater.io/v1/actions', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer sk_live_...',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        idempotency_key: `onboarding-${userId}-${step.template}`,
        intent: { delay: step.delay },
        request: {
          method: 'POST',
          url: 'https://api.yourapp.com/emails/send',
          body: {
            user_id: userId,
            email: email,
            template: step.template
          }
        }
      })
    });
  }
}
```

**Key points:**
- Each email gets its own idempotency key
- Cancel remaining emails if user unsubscribes
- Your email endpoint can skip if user already converted

---

## Invoice Payment Reminder

Remind about upcoming payment, escalate if overdue.

```json
{
  "mode": "gated",
  "idempotency_key": "invoice-INV-2025-001-reminder",
  "intent": {
    "execute_at": "2025-01-28T09:00:00Z"
  },
  "gate": {
    "message": "Invoice INV-2025-001 ($1,500) is due in 3 days. Click Confirm to mark as scheduled for payment.",
    "recipients": ["billing@customer.com"],
    "escalation_contacts": ["accounts-receivable@yourcompany.com"],
    "escalate_after_hours": 72,
    "token_expiry_days": 7
  }
}
```

**Key points:**
- Schedule reminder a few days before due date
- Escalate to your AR team if customer doesn't respond
- Use `execute_at` for a specific date rather than relative delay
