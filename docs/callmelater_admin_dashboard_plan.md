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
  - `is_admin = true` column on users table
  - or email allowlist in config

This dashboard must **never** be exposed to normal users.

### Implementation
- `EnsureUserIsAdmin` middleware
- Applied to all `/admin` and `/api/admin` routes
- Returns 403 Forbidden for non-admin users

---

## Scope (V1)

Keep it **small, factual, and boring**.

### Core Metrics (Counters)

**Users:**
- Total users
- New users (last 7 / 15 / 30 days)
- Users by plan (Free / Pro / Business / Enterprise)

**Actions:**
- Total actions created
- HTTP actions created
- Reminders created
- Actions executed successfully
- Actions failed
- Actions cancelled
- Failure rate % (failed / total attempted)

**Reminders:**
- Reminders sent
- Reminder responses:
  - confirmed
  - declined
  - snoozed
- Escalations triggered

**Revenue (from Stripe):**
- Active subscriptions by plan
- MRR (Monthly Recurring Revenue)

These numbers answer:
> "Is anyone using this — and does it work?"

---

### Trends (Simple Time Series)

Lightweight daily trends (last 30 days):
- Actions created per day
- Reminders sent per day
- Reminder responses per day
- Failures per day
- New users per day
- New subscriptions per day

Simple bar or line charts are sufficient.
Tables are acceptable for v1.

---

### Operational Signals (Critical)

These help detect problems **before** users complain:

**Action Health:**
- Actions stuck in `resolved` for too long (scheduled but not dispatched)
- Actions stuck in `awaiting_response` (reminder sent, no response)
- High retry counts on HTTP actions (> 3 attempts)
- Spike in failed executions (vs 7-day average)
- Escalations increasing unexpectedly

**Queue Health:**
- Jobs pending in queue
- Jobs failed (last hour)
- Average job processing time
- Queue worker status

**System Health:**
- Database connection status
- Redis connection status
- Disk usage (logs)

This is where the dashboard provides the most value.

---

## Data Sources

The admin dashboard queries **production tables directly**.

No exports.
No data warehouse.
No replication required at this stage.

Typical queries:
```sql
-- User counts
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM users WHERE created_at >= now() - interval '7 days';

-- Action stats
SELECT COUNT(*) FROM scheduled_actions WHERE resolution_status = 'failed';
SELECT COUNT(*) FROM scheduled_actions WHERE resolution_status = 'executed';

-- Failure rate
SELECT
  COUNT(*) FILTER (WHERE resolution_status = 'failed') as failed,
  COUNT(*) FILTER (WHERE resolution_status IN ('executed', 'failed')) as total
FROM scheduled_actions;

-- Reminder events
SELECT event_type, COUNT(*) FROM reminder_events GROUP BY event_type;

-- Stuck actions (resolved but past due)
SELECT COUNT(*) FROM scheduled_actions
WHERE resolution_status = 'resolved'
AND execute_at_utc < now() - interval '5 minutes';

-- Daily trends
SELECT DATE(created_at) as day, COUNT(*)
FROM scheduled_actions
WHERE created_at >= now() - interval '30 days'
GROUP BY DATE(created_at)
ORDER BY day;
```

These queries are fast and safe at early scale.

---

## API Endpoints

```
GET /api/admin/stats/overview     — All counters
GET /api/admin/stats/trends       — Daily time series (last 30 days)
GET /api/admin/stats/health       — Operational signals
GET /api/admin/stats/queue        — Queue status
```

All endpoints require admin authentication.

---

## What NOT to Build (V1)

Do **not** include:

- Customer-facing analytics
- CSV exports
- Role or permission management UI
- Cohort or retention analysis
- AI-generated insights
- User impersonation
- Action editing/deletion

All of these can wait.

---

## Evolution Path

### Phase 1 — Now
- In-app admin dashboard
- Hard admin-only access
- Live counters and basic charts
- Focus on health and usage
- Queue monitoring

### Phase 2 — After Early Traction
- Append-only event log (if needed)
- Pre-aggregated daily stats table
- Simple retention metrics
- Alert notifications (email/Slack when thresholds breached)

### Phase 3 — Only If Required
- External BI (Metabase, Looker, BigQuery)
- Long-term historical analysis
- Team-level dashboards
- Customer-facing usage analytics

Do not jump to Phase 3 prematurely.

---

## UI Structure

Single-page dashboard with sections:

```
/admin
├── Overview (counters grid)
├── Trends (charts)
├── Health (alerts/warnings)
└── Queue (worker status)
```

### Visual Indicators
- Green: healthy / normal
- Yellow: warning / elevated
- Red: critical / action required

### Thresholds (configurable)
- Stuck actions warning: > 10 actions past due by 5+ minutes
- Failure rate warning: > 5%
- Queue backlog warning: > 100 pending jobs

---

## Design Principles

- Prefer clarity over beauty
- Prefer live truth over delayed reports
- Prefer boring, correct queries over clever dashboards
- Never reuse admin dashboard code for customer dashboards
- Show rates/percentages, not just raw counts
- Highlight anomalies, not just totals

Admin dashboards answer:
> "Is the system and business healthy?"

Customer dashboards answer:
> "Did my action run?"

They must stay separate.

---

## Summary

The admin dashboard should be:
- internal
- minimal
- factual
- trustworthy
- actionable

If built this way, it becomes a **daily operational tool**, not a forgotten page.

This plan is sufficient to implement the admin dashboard without overengineering.
