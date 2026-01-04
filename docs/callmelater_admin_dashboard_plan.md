# CallMeLater — Admin Dashboard Plan

## Purpose

The admin dashboard is an **internal-only tool** for monitoring the health, usage, and growth of the CallMeLater platform.

It is **not** a customer-facing analytics product.  
Its goal is to help the operator answer, at a glance:

- Is the system healthy?
- Are users actually using it?
- Is something silently breaking?
- Is adoption growing or stalling?

---

## High-level Recommendation

**Build a small admin dashboard inside the main application**, protected behind a strict admin-only gate.

- No separate service
- No external BI tool (for now)
- No public access

This keeps the dashboard:
- close to the data
- easy to evolve
- cheap to maintain
- operationally trustworthy

---

## Location & Access

### URL
```
/admin
```

### Access Control
- Restricted to admin users only
- Simple rule for v1:
  - `is_admin = true`
  - or email allowlist

This dashboard must **never** be exposed to normal users.

---

## Scope (V1)

Keep it **small, factual, and boring**.

### Core Metrics (Counters)

- Total users
- New users:
  - last 7 days
  - last 15 days
  - last 30 days
- Total actions created
- HTTP actions created
- Reminders created
- Actions executed successfully
- Actions failed
- Actions cancelled
- Reminders sent
- Reminder responses:
  - confirmed
  - declined
  - snoozed
- Escalations triggered

These numbers answer:
> “Is anyone using this — and does it work?”

---

### Trends (Simple Time Series)

Lightweight daily or weekly trends:
- Actions created per day
- Reminders sent per day
- Reminder responses per day
- Failures per day
- New users per day

Simple bar or line charts are sufficient.  
Tables are acceptable for v1.

---

### Operational Signals (Very Important)

These help detect problems **before** users complain:

- Actions stuck in `resolved` for too long
- Actions stuck in `awaiting_response`
- High retry counts on HTTP actions
- Spike in failed executions
- Escalations increasing unexpectedly

This is where the dashboard provides the most value.

---

## Data Sources

The admin dashboard queries **production tables directly**.

No exports.  
No data warehouse.  
No replication required at this stage.

Typical queries:
```sql
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM users WHERE created_at >= now() - interval '7 days';
SELECT COUNT(*) FROM scheduled_actions WHERE resolution_status = 'failed';
SELECT COUNT(*) FROM reminder_events WHERE event_type = 'confirmed';
```

These queries are fast and safe at early scale.

---

## What NOT to Build (V1)

Do **not** include:

- Customer-facing analytics
- CSV exports
- Role or permission management UI
- Cohort or retention analysis
- AI-generated insights

All of these can wait.

---

## Evolution Path

### Phase 1 — Now
- In-app admin dashboard
- Hard admin-only access
- Live counters and basic charts
- Focus on health and usage

### Phase 2 — After Early Traction
- Append-only event log (if needed)
- Pre-aggregated daily stats
- Simple retention metrics

### Phase 3 — Only If Required
- External BI (Metabase, Looker, BigQuery)
- Long-term historical analysis
- Team-level dashboards

Do not jump to Phase 3 prematurely.

---

## Design Principles

- Prefer clarity over beauty
- Prefer live truth over delayed reports
- Prefer boring, correct queries over clever dashboards
- Never reuse admin dashboard code for customer dashboards

Admin dashboards answer:
> “Is the system and business healthy?”

Customer dashboards answer:
> “Did my action run?”

They must stay separate.

---

## Summary

The admin dashboard should be:
- internal
- minimal
- factual
- trustworthy

If built this way, it becomes a **daily operational tool**, not a forgotten page.

This plan is sufficient to implement the admin dashboard without overengineering.
