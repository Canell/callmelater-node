# CallMeLater

## Make things happen later — without surprises

CallMeLater lets you schedule future actions and confirmations you can actually rely on.

---

## "Do this later" is where things usually break

You plan to:
- call an API in three days
- clean up data after a trial ends
- remind someone to confirm an action

And then:
- the server restarts
- a job fails silently
- the reminder is ignored
- no one knows what happened

That's not a mistake — it's how most systems work.

---

## CallMeLater makes time-based work dependable

Schedule an action once.
CallMeLater takes responsibility for the rest.

- It keeps trying when things fail
- It remembers even if systems restart
- It tells you exactly what happened
- It never disappears silently

You always know the state of your action.

---

## One simple concept: actions

Everything in CallMeLater is an **action scheduled for the future**.

### Automated calls

Trigger an API call minutes, days, or months from now.

- Send your own data
- Get clear success or failure signals
- See every attempt

Use it for:
- trial expirations
- follow-ups
- delayed workflows
- clean-up jobs

---

### Human confirmation — without friction

Sometimes a person needs to decide first.

Send a reminder with clear options:
- Yes
- No
- Snooze

Recipients respond with **one click**.
No account. No login. No setup.

Each response automatically triggers a webhook, reschedules the action, or escalates if no one responds.

---

## Built for reliability

### Automatic retries with exponential backoff

When requests fail, CallMeLater automatically retries with increasing delays:
1 min → 5 min → 15 min → 1 hour → 4 hours

You configure the max attempts. We handle the rest.

### Idempotency built-in

Use idempotency keys to prevent duplicate actions. Cancel or check status using the same key.

```json
{
  "idempotency_key": "trial-end-user-42",
  "type": "http",
  ...
}
```

### Webhook signatures

Every outgoing request is signed with HMAC-SHA256. Verify authenticity on your end:

```
X-CallMeLater-Signature: sha256=<hmac>
X-CallMeLater-Action-Id: <uuid>
X-CallMeLater-Timestamp: <unix>
```

---

## Designed to be trusted

CallMeLater is built so you never have to ask:

> "Did this run?"

You can always see:
- what was scheduled
- when it was supposed to happen
- whether it ran
- who responded
- what failed and why

No hidden behavior. No guessing.

---

## Made for teams

- Escalate when no one responds
- Require one confirmation — or everyone's
- Keep a full activity history

Perfect for shared responsibility and accountability.

---

## What CallMeLater is not

- Not a task manager
- Not a habit tracker
- Not a cron replacement

It focuses on one thing:
**reliable actions in the future.**

---

## Start small

Use it for one delayed call.
One reminder that matters.

If it saves you once, it will earn its place.

---

## Get started in under a minute

**Send your first delayed webhook in 60 seconds.**

No agents. No cron jobs. No infrastructure to manage.

```bash
curl https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "type": "http",
    "idempotency_key": "trial-end-user-42",
    "intent": {
      "preset": "tomorrow"
    },
    "http_request": {
      "method": "POST",
      "url": "https://example.com/webhook",
      "body": {
        "event": "trial_expired",
        "user_id": 42
      }
    }
  }'
```

That's it.
CallMeLater will deliver the request — and tell you exactly what happened.

---

## Flexible scheduling

Schedule actions your way:

| Method | Example | Description |
|--------|---------|-------------|
| Preset | `"preset": "tomorrow"` | Tomorrow at 9am in your timezone |
| Preset | `"preset": "next_monday"` | Next Monday at 9am |
| Preset | `"preset": "3d"` | In 3 days |
| Delay | `"delay": "2h"` | In 2 hours from now |
| Exact | `"execute_at": "2025-04-01T09:00:00Z"` | Specific UTC time |

All presets respect your timezone when provided.

---

## Pricing

### Free

**$0/month**
- 100 actions/month
- 3 retry attempts
- Email reminders only
- 7-day history

### Pro

**$29/month**
- 5,000 actions/month
- 10 retry attempts
- Email + SMS reminders
- 90-day history
- Webhook signatures
- Priority support

### Business

**$99/month**
- 25,000 actions/month
- Unlimited retry attempts
- Email + SMS reminders
- 1-year history
- Webhook signatures
- Team features
- Escalation rules
- Dedicated support

### Enterprise

**Custom pricing**
- Unlimited actions
- Custom retention
- SLA guarantees
- On-premise option
- Dedicated infrastructure

---

# Documentation Structure (Docusaurus)

```
docs/
├── intro.md                    # Quick start guide
├── concepts/
│   ├── actions.md              # What actions are
│   ├── states.md               # Action lifecycle
│   ├── retries.md              # Retry strategies
│   ├── reminders.md            # Human confirmations
│   └── idempotency.md          # Preventing duplicates
├── api/
│   ├── authentication.md       # API tokens
│   ├── create-action.md        # POST /v1/actions
│   ├── list-actions.md         # GET /v1/actions
│   ├── get-action.md           # GET /v1/actions/{id}
│   ├── cancel-action.md        # DELETE /v1/actions
│   └── webhooks.md             # Incoming webhook format
├── guides/
│   ├── trial-expiration.md     # Common use case
│   ├── approval-workflows.md   # Reminder patterns
│   ├── scheduled-reports.md    # Recurring patterns
│   └── error-handling.md       # Handling failures
└── reference/
    ├── rate-limits.md          # API limits
    ├── security.md             # SSRF, signatures
    └── changelog.md            # Version history
```

---

## Documentation philosophy

- Homepage: clarity, trust, decision
- Docs: precision, guarantees, details

A user should:
1. Understand CallMeLater in 60 seconds
2. Trust it
3. Confirm details in the docs
4. Integrate in minutes
