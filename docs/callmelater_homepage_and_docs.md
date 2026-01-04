# CallMeLater

## Make things happen later — without surprises

CallMeLater lets you schedule future actions and confirmations you can actually rely on.

---

## “Do this later” is where things usually break

You plan to:
- call an API in three days
- clean up data after a trial ends
- remind someone to confirm an action

And then:
- the server restarts
- a job fails silently
- the reminder is ignored
- no one knows what happened

That’s not a mistake — it’s how most systems work.

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

## Designed to be trusted

CallMeLater is built so you never have to ask:

> “Did this run?”

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
- Require one confirmation — or everyone’s
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
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "http",
    "deliver_at": "2025-04-01T09:00",
    "timezone": "Europe/Brussels",
    "request": {
      "method": "POST",
      "url": "https://example.com/webhook",
      "body": {
        "event": "trial_expired",
        "user_id": 42
      }
    }
  }'
```

That’s it.  
CallMeLater will deliver the request — and tell you exactly what happened.

---

# Documentation

## Recommended tooling

For documentation, use **Docusaurus**.

### Why Docusaurus

- Markdown-based
- Versioned documentation
- Built-in search
- Clean navigation
- Excellent for API docs
- Easy to host and maintain

It fits perfectly for:
- API references
- Concepts (actions, states, retries)
- Guides and common use cases
- Reliability and guarantees

---

### Suggested documentation structure

```
docs/
  intro.md
  concepts/
    actions.md
    states.md
    retries.md
    reminders.md
  api/
    create-action.md
    cancel-action.md
    webhooks.md
  guides/
    common-use-cases.md
    cancellation.md
    idempotency.md
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
