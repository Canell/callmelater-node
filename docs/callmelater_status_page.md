# CallMeLater — Status Page Specification

## Purpose

The Status page provides **clear, trustworthy visibility** into the health and uptime of CallMeLater.

Its goals are:
- Build trust with developers and teams
- Reduce support noise during incidents
- Communicate incidents transparently and calmly
- Align with CallMeLater’s reliability-first positioning

This is not a monitoring dashboard — it is a **communication tool**.

---

## Scope (V1)

The Status page answers three questions:

1. Is CallMeLater working right now?
2. Which parts are affected (if any)?
3. What happened recently?

Nothing more.

---

## Where the Status Page Lives

### Recommended
```
https://status.callmelater.io
```

**Why**
- Accessible even if the main app has issues
- Professional SaaS convention
- Can be served with aggressive caching or static fallback

### Implementation note
The status page **can still be powered by the same backend**, but should:
- avoid auth
- avoid heavy API dependencies
- fail gracefully (cached or static render)

---

## System Components to Expose

Keep components **high-level and user-facing**.

Example components:
- API
- Scheduler / Dispatcher
- Webhook Delivery
- Email Notifications
- Dashboard

Each component has a status:
- `operational`
- `degraded`
- `outage`

No internal services, queues, or database names should be shown.

---

## Data Model (Minimal & Sufficient)

### 1. system_components
Represents what users see.

Fields:
- `id`
- `name` (e.g. “Webhook Delivery”)
- `slug`
- `current_status` (`operational | degraded | outage`)
- `updated_at`

---

### 2. status_events (current + history)
Tracks changes over time.

Fields:
- `id`
- `component_id`
- `status`
- `message` (short human-readable explanation)
- `created_at`

This table powers:
- the current status
- the incident history
- uptime calculations (later)

---

### 3. incidents (optional but recommended)
Higher-level grouping for real incidents.

Fields:
- `id`
- `title`
- `impact` (minor | major | critical)
- `summary`
- `started_at`
- `resolved_at` (nullable)
- `created_at`

An incident can reference multiple components.

---

## How Status Is Updated

### V1 — Manual (Recommended)
In early stages, **manual updates are best**.

- Admin dashboard toggle per component
- Optional incident creation form
- Short free-text message explaining impact

Why manual works:
- Fewer false positives
- Clear communication
- No automation bugs during early growth

---

### V2 — Assisted / Semi-Automated (Later)
Possible triggers:
- sustained dispatcher backlog
- high retry rate
- external provider outage (e.g. email)

Automation should:
- suggest status changes
- not apply them blindly

Humans stay in the loop.

---

## How the Status Page Fetches Data

### Backend
Expose a **single public endpoint**, e.g.:

```
GET /public/status
```

Returns:
- list of components
- current status
- active incidents
- recent incident history

Example response shape:
```json
{
  "components": [
    { "name": "Webhook Delivery", "status": "operational" },
    { "name": "Email Notifications", "status": "degraded" }
  ],
  "incidents": [
    {
      "title": "Delayed webhooks",
      "started_at": "2026-01-04T10:12:00Z",
      "status": "resolved"
    }
  ]
}
```

---

### Frontend
The status page should:
- fetch this endpoint
- cache aggressively (CDN / browser)
- display last-updated timestamp

If the fetch fails:
- show last cached state
- never show a blank page

---

## Should We Keep and Show Status History?

### Yes — but keep it short and human-readable.

Recommended:
- show incidents from the last **30–90 days**
- no raw uptime charts at first
- no minute-by-minute timelines

Example incident entry:
> **Jan 4, 2026 — Delayed webhook delivery**  
> Webhook execution was delayed for approximately 22 minutes due to queue congestion. No data was lost.

Transparency > precision.

---

## Uptime Display (V1)

Keep uptime **simple and conservative**.

Examples:
- “Operational over the last 7 days”
- “99.9% uptime (last 30 days)”

Avoid:
- per-component SLAs
- guarantees you can’t enforce yet

---

## Security & Abuse Considerations

- Status endpoint must be read-only
- No internal identifiers or stack details
- No per-user data
- Rate-limited but cache-friendly

---

## Relationship with Monitoring

Monitoring detects problems.  
The Status page **communicates** them.

Do not confuse the two.

The Status page should always prioritize:
- clarity
- calm tone
- human explanation

---

## Copy & Tone Guidelines

Status messaging should be:
- factual
- short
- non-defensive
- explicit about impact

Good:
> “Webhook delivery was delayed. Execution resumed at 10:42 UTC.”

Bad:
> “We experienced unexpected issues in our Redis cluster.”

---

## Future Enhancements (Optional)

- Email / webhook notifications for incidents
- RSS feed
- Automatic incident creation (human-reviewed)
- Dogfooding CallMeLater for internal alerts

None are required for launch.

---

## Final Recommendation

Add the Status page early.

It is:
- a trust signal
- a support load reducer
- a natural extension of CallMeLater’s mission

Keep it simple, honest, and boring — that’s what reliability looks like.
