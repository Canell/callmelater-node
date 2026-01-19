---
sidebar_position: 6
---

# What CallMeLater Does NOT Do

Setting clear expectations helps you choose the right tool. Here's what CallMeLater is **not** designed for.

## Not a Cron Replacement

CallMeLater schedules **one-off actions**, not recurring jobs.

| Use Case | CallMeLater? | Better Alternative |
|----------|--------------|-------------------|
| "Run this backup every night at 2am" | No | Cron, Cloud Scheduler, GitHub Actions |
| "Expire this user's trial in 14 days" | **Yes** | — |
| "Send weekly reports every Monday" | Partial* | Cron triggers CallMeLater per-report |

\* You can chain actions (each report schedules the next), but native cron is simpler for pure recurrence.

**Why not?** Recurring jobs need different guarantees (catch-up on missed runs, distributed locking). CallMeLater optimizes for "do this specific thing at this specific time."

---

## Not a Workflow Engine

CallMeLater executes **single actions**, not multi-step workflows.

```
❌ CallMeLater does NOT do this:
   Step 1 → Step 2 → Step 3 (with branching, retries per step, rollback)

✅ CallMeLater DOES do this:
   Schedule Step 1 → Your system handles the rest
```

**For workflows, consider:** Temporal, AWS Step Functions, Inngest, or your own state machine.

**What CallMeLater offers:** A reliable way to trigger the *start* of a workflow, or to schedule individual steps that your orchestrator manages.

---

## Not a Message Queue

CallMeLater is for **scheduled** delivery, not instant message passing.

| Pattern | CallMeLater? | Better Alternative |
|---------|--------------|-------------------|
| "Process this now, with retries" | No | SQS, RabbitMQ, Redis queues |
| "Process this in 3 hours" | **Yes** | — |
| "Fan out to 1000 workers immediately" | No | Pub/Sub, Kafka |

**Why not?** Message queues optimize for throughput and immediate processing. CallMeLater optimizes for durability over time.

---

## Not a Monitoring/Alerting System

CallMeLater can *trigger* alerts, but it doesn't *detect* problems.

```
❌ "Alert me when CPU > 90%"        → Use Datadog, PagerDuty, Prometheus
✅ "Remind ops to check the deploy" → CallMeLater
```

**What CallMeLater offers:** Human reminders with escalation. Useful for scheduled check-ins, not real-time monitoring.

---

## Not an AI Decision Maker

CallMeLater executes what you tell it to. It doesn't decide *whether* to act.

```
❌ "Cancel the order if it looks fraudulent"
✅ "Call my fraud-check endpoint in 30 minutes"
```

Your system makes decisions. CallMeLater makes sure they happen on time.

---

## No Long-Running Jobs

Actions must complete within **30 seconds**. CallMeLater waits for your webhook response.

| Task | CallMeLater? | Approach |
|------|--------------|----------|
| "Start a video encoding job" | **Yes** | Webhook triggers async job, returns 202 |
| "Wait for video encoding to finish" | No | Your job notifies completion separately |
| "Run a 10-minute data migration" | No | Webhook starts it, runs in background |

**Pattern:** Use CallMeLater to *trigger* long-running work, not to *perform* it.

---

## Summary

CallMeLater is a **scheduling primitive** — reliable, durable, and focused.

| CallMeLater IS | CallMeLater IS NOT |
|----------------|-------------------|
| Scheduled webhooks | Cron scheduler |
| Human reminders | Workflow engine |
| Durable timers | Message queue |
| Trigger mechanism | Monitoring system |
| One-off actions | Recurring jobs |

Use it for what it's good at: making sure something happens at the right time, even if your server restarts, your deploy fails, or everyone goes home for the weekend.
