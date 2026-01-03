# CallMeLater

> **Schedule actions — or decisions — for the future.**

CallMeLater is a developer‑first SaaS that lets you schedule **durable, long‑term actions** (HTTP calls or human reminders) without cron jobs, background workers, or complex infrastructure.

It combines two powerful primitives:

1. **Deferred HTTP calls** ("call this API later")
2. **Interactive human reminders** ("ask a person and wait for a response")

Inspired by the simplicity and trust of Healthchecks.io, CallMeLater focuses on clarity, reliability, and low cognitive load.

---

## 1. The Problem

Developers and small teams constantly need to do things *later*:

- Send follow‑ups
- Retry failed actions
- Clean up resources months later
- Remind humans to confirm critical actions
- Track acknowledgements (yes / no / snooze)

Today, this requires:
- cron jobs
- background workers
- queues with long delays
- polling databases
- unreliable manual reminders

These solutions are fragile, stateful, and error‑prone — especially in serverless or small‑team setups.

---

## 2. The Solution

**CallMeLater** provides a durable, stateless primitive:

> "Schedule this action or question for the future, and guarantee attempts, retries, and logs."

You send the instruction once. CallMeLater takes care of execution, retries, escalation, and auditability.

---

## 3. Target Users

### Primary (V1 focus)
- Indie SaaS founders
- Small SaaS teams (2–20 devs)
- Serverless / API‑first apps
- Agencies building internal tools

### Secondary (after traction)
- Ops & compliance teams
- Managers needing accountability workflows

### Explicitly NOT targeting
- General consumers / habit trackers
- Full todo or productivity apps
- Enterprise procurement‑driven organizations

---

## 4. Core Product Concepts

### 4.1 Scheduled HTTP Calls

Schedule a **one‑off HTTP request** to be executed in the future.

**Capabilities:**
- Absolute or relative scheduling
- Custom HTTP method, headers, payload
- Retry policy (max attempts, exponential backoff)
- Cancel / reschedule
- Webhook signing
- Full delivery logs

**Guarantee:**
- Attempts + retries + logs (not magical delivery)

---

### 4.2 Interactive Reminders (Key Differentiator)

Send a scheduled message to a human and wait for a response.

**Channels:**
- Email (v1)
- SMS (paid tiers)

**Actions:**
- Yes
- No
- Snooze
- Custom message (optional)

Each action can:
- Trigger a webhook
- Reschedule the reminder
- Escalate to another person or channel

This models **human acknowledgement over time**, something most tools do poorly.

---

### 4.3 Team Reminders

For teams and accountability:
- Multiple recipients
- First response wins OR everyone must confirm
- Deadline‑based escalation
- Response history and audit log

---

## 5. UX Principles (Inspired by Healthchecks.io)

- **List‑first dashboard**
- **Status‑driven UI**
- **Minimal cognitive load**
- **API‑first, UI‑friendly**

No charts. No gamification. No clutter.

---

## 6. UI Overview

### 6.1 Main Dashboard

Single table view showing:
- Name
- Type (HTTP / Reminder)
- Status (Scheduled, Waiting, Snoozed, Delivered, Failed)
- Next execution time
- Channel icons

At‑a‑glance trust: "Is everything OK right now?"

---

### 6.2 Create New

Two clear paths:
- Scheduled HTTP Call
- Interactive Reminder

---

### 6.3 HTTP Call Detail Page

Sections:
- Basics (name, description, time)
- Request (method, URL, headers, body)
- Retry policy
- Advanced (idempotency, signing)
- Execution log (timeline)

---

### 6.4 Reminder Detail Page

Sections:
- Message & recipients
- Delivery channels
- Actions (Yes / No / Snooze / Custom)
- Escalation rules
- Response log

Email/SMS interactions use signed one‑click buttons (no login required).

---

## 7. What CallMeLater Is NOT

Deliberately excluded from v1:

- Full todo lists
- Projects or kanban boards
- Habit tracking
- Native mobile apps
- Complex workflow builders
- Analytics or AI features

This discipline keeps the product focused and shippable.

---

## 8. Technical Stack

### Backend
- **Laravel** (core API, jobs, auth)
- **MySQL** (durable storage, audit logs)
- **Redis** (locks, short‑term scheduling)

**Scheduling strategy:**
- Store future actions in DB
- Minute‑based dispatcher selects due items
- Idempotent delivery workers

---

### Frontend
- **Vue.js**
- **Bootstrap**
- Simple admin‑style UI

---

### Notifications
- Email: SES / Mailgun / Postmark
- SMS (paid): Twilio or equivalent
- Push notifications: not v1

---

### Security & Abuse Prevention

Mandatory from day one:
- Domain allow‑listing
- Block private IP ranges
- Rate limiting per destination
- Payload size limits
- Encryption at rest

---

## 9. Pricing Tiers

Pricing reflects the **reliability, auditability, and trust** provided by the system. This is deferred execution infrastructure, not a reminder app.

### Free (€0)
- 100 scheduled actions / month
- 7-day max delay
- Email only (no SMS)

---

### Pro (€15–25 / month)
- Unlimited scheduled actions
- Delays up to 2 years
- Interactive reminders (Yes / No / Snooze)
- Webhook signing
- Retry policies & delivery logs
- 90-day retention

---

### Team (€49–79 / month)
- Everything in Pro
- Team dashboards
- Confirmation modes (first response / all required)
- Escalation rules
- SMS reminders included
- Audit log export
- Priority execution window

---

## 10. Business Model Logic

- Low infrastructure costs (mostly idle)
- Variable costs (email/SMS) passed through
- Developer audience → low support overhead

Example:
- 100 Pro users × €20 = €2,000 / month
- 20 Team users × €60 = €1,200 / month

---

## 11. Go‑to‑Market Strategy

### Early adopters
- Indie SaaS founders
- Serverless developers
- Small teams tired of cron & workers

### Channels
- Dev communities
- Blog posts about cron failures
- Demo showing snooze → reschedule loop

---

## 12. Final Verdict

CallMeLater is:
- A focused, developer‑first product
- Solving a real infrastructure + human‑workflow gap
- Suitable for a solo founder
- Defensible through long‑term scheduling and human acknowledgement

It succeeds if it stays disciplined and avoids becoming a generic productivity app.

---

## 13. Wall-Clock Intent Design (V1)

### 13.1 Design Principles

- Wall-clock scheduling models **human intent**, not timestamps.
- Intent is expressed as an explicit command with a closed grammar.
- No recurring schedules in v1.
- Resolution is a separate, explicit step.

---

### 13.2 Supported Commands (V1)

#### A. Snooze Presets

```json
{
  "intent_type": "wall_clock",
  "timezone": "Europe/Brussels",
  "command": {
    "kind": "snooze_preset",
    "preset": "next_monday"
  }
}
```

Allowed presets:
- `tomorrow`
- `next_week`
- `next_monday`

---

#### B. Relative Delays

```json
{
  "intent_type": "wall_clock",
  "timezone": "Europe/Brussels",
  "command": {
    "kind": "relative_delay",
    "unit": "day",
    "value": 3
  }
}
```

Allowed units:
- `minute`
- `hour`
- `day`

---

### 13.3 Explicitly Unsupported (V1)

- Recurring schedules
- Ordinal weekday rules (e.g. "first Monday")
- Cron expressions
- Natural language parsing

Unknown commands must be rejected with a clear error.

---

## 14. Resolution & Execution Lifecycle

### 14.1 Explicit Lifecycle States

The lifecycle is explicit and models both system execution and human acknowledgement.

Allowed values for `resolution_status`:
- `pending_resolution` — wall-clock intent not yet resolved
- `resolved` — executable time known, waiting for execution
- `awaiting_response` — reminder delivered, waiting for human action
- `executed` — final success (HTTP succeeded or reminder confirmed/declined)
- `cancelled` — user or API cancelled before completion
- `expired` — reminder link expired without response
- `failed` — permanent failure (max retries exceeded, invalid destination)

Only `resolved` actions are eligible for execution by the dispatcher.

---

### 14.2 Data Model (Core Fields)

```text
scheduled_actions
- id
- owner_user_id          (uuid, required)
- owner_team_id          (uuid, nullable)
- type                   (http | reminder)
- intent_type            (absolute | wall_clock)
- intent_payload         (json)
- timezone               (nullable)
- resolution_status      (pending_resolution | resolved | awaiting_response | executed | cancelled | expired | failed)
- execute_at_utc         (nullable)
- executed_at_utc        (nullable)
- failure_reason         (nullable)

-- HTTP retry & idempotency
- idempotency_key        (text, nullable)
- attempt_count          (int, default 0)
- max_attempts           (int)
- last_attempt_at        (timestamptz, nullable)
- next_retry_at          (timestamptz, nullable)

-- Reminder behavior
- confirmation_mode      (first_response | all_required)
- escalation_rules       (json, nullable)
- snooze_count           (int, default 0)
- max_snoozes            (int, default 5)
- token_expires_at       (timestamptz)

- created_at
```

---

### 14.3 Token Expiry

Response tokens expire **7 days** after the reminder is sent by default. This can be configured per-action via the API.

When a token expires:
- The response link shows "This link has expired"
- The user is offered a login option to manually reschedule
- An `expired` event is logged in `reminder_events`

---

### 14.4 Lifecycle Example

**Creation**
```
intent_type = wall_clock
resolution_status = pending_resolution
execute_at_utc = null
```

**After Resolution**
```
resolution_status = resolved
execute_at_utc = 2025-03-18T08:00:00Z
```

**Reminder Sent**
```
resolution_status = awaiting_response
token_expires_at = 2025-03-25T08:00:00Z
```

**Human Response**
```
resolution_status = executed
executed_at_utc = 2025-03-18T08:12:00Z
```

---

## 15. Resolver & Dispatcher Pseudocode

### 15.1 Intent Resolver

```pseudo
function resolveIntent(action):
    assert action.intent_type == 'wall_clock'
    tz = action.timezone
    now_local = now().in_timezone(tz)

    switch action.command.kind:
        case 'relative_delay':
            target_local = now_local + action.command.value * action.command.unit

        case 'snooze_preset':
            if preset == 'tomorrow':
                target_local = tomorrow at same local time
            if preset == 'next_week':
                target_local = now_local + 7 days
            if preset == 'next_monday':
                target_local = next Monday at same local time

        default:
            action.resolution_status = 'failed'
            action.failure_reason = 'unsupported_command'
            save(action)
            return

    action.execute_at_utc = convert_to_utc(target_local)
    action.resolution_status = 'resolved'
    save(action)
```

---

### 15.2 Dispatcher Loop

```pseudo
function dispatcherTick():
    actions = select scheduled_actions
        where resolution_status = 'resolved'
        and (
            execute_at_utc <= now()
            or next_retry_at <= now()
        )
        for update skip locked

    for action in actions:
        if action.type == 'http':
            executeHttp(action)
        if action.type == 'reminder':
            sendReminder(action)
```

---

### 15.3 HTTP Execution

```pseudo
function executeHttp(action):
    attempt = action.attempt_count + 1
    record delivery_attempt

    result = http_call(action)

    if result.success:
        action.resolution_status = 'executed'
        action.executed_at_utc = now()
    else:
        action.attempt_count += 1
        action.last_attempt_at = now()

        if action.attempt_count >= action.max_attempts:
            action.resolution_status = 'failed'
            action.failure_reason = 'max_attempts_exceeded'
        else:
            action.next_retry_at = compute_backoff(action)
            action.resolution_status = 'resolved'

    save(action)
```

---

### 15.4 Reminder Delivery, Snooze & Escalation

```pseudo
function sendReminder(action):
    deliver notification
    insert reminder_event(type='sent')
    action.resolution_status = 'awaiting_response'
    action.token_expires_at = now() + 7 days
    save(action)

function handleResponse(token, response, tz):
    action = lookup(token)

    -- Check token expiry
    if action.token_expires_at < now():
        insert reminder_event(type='expired', timezone=tz)
        action.resolution_status = 'expired'
        save(action)
        return error("Token expired")

    -- Check valid state
    if action.resolution_status not in ['awaiting_response']:
        return noop

    insert reminder_event(type=response, timezone=tz)

    if response == 'snooze':
        action.snooze_count += 1
        if action.snooze_count > action.max_snoozes:
            action.resolution_status = 'expired'
            action.failure_reason = 'max_snoozes_exceeded'
        else:
            action.intent_payload = build_new_intent(response)
            action.resolution_status = 'pending_resolution'
    else:
        action.resolution_status = 'executed'
        action.executed_at_utc = now()

    save(action)
```

---

## 16. Critical Test Cases

### Timezone & DST
- Snooze across DST jump forward (missing hour)
- Snooze across DST jump backward (duplicated hour)
- Server timezone different from user timezone

### Snooze Behavior
- Snooze "tomorrow" at 23:30
- Snooze "next Monday" on a Monday morning
- Snooze with invalid or missing timezone
- Snooze when max_snoozes already reached

### Token Expiry
- Response attempted after token_expires_at
- Response attempted with valid token
- Token expiry edge case (exactly at expiry time)

### Safety & Correctness
- Ensure no action executes twice
- Ensure unresolved intents never execute
- Ensure failed resolutions surface in dashboard

---

## 17. Summary

This design:
- Models human intent explicitly
- Avoids ambiguous timestamps
- Prevents schema drift
- Makes execution and resolution observable

It provides a safe foundation for future recurrence features without committing to them prematurely.

---

## 18. Public API Specification (OpenAPI – Simplified)

### Design Note

All deferred operations are represented as **actions**. HTTP calls and reminders are distinguished by the `type` field. This keeps the API consistent and avoids duplicated concepts.

---

### 18.1 Authentication

All API requests require a Bearer token:

```
Authorization: Bearer sk_live_...
```

---

### 18.2 Create Scheduled HTTP Call

**POST** `/v1/actions`

```json
{
  "type": "http",
  "idempotency_key": "cleanup-user-42",
  "deliver_at": "2025-03-18T09:00",
  "timezone": "Europe/Brussels",
  "request": {
    "method": "POST",
    "url": "https://api.example.com/cleanup",
    "headers": {"X-Signature": "abc"},
    "body": {"user_id": 42}
  },
  "retry": {
    "max_attempts": 5,
    "strategy": "exponential"
  }
}
```

**Response**
```json
{
  "id": "act_123",
  "resolution_status": "resolved",
  "execute_at_utc": "2025-03-18T08:00:00Z"
}
```

---

### 18.3 Create Interactive Reminder

**POST** `/v1/actions`

```json
{
  "type": "reminder",
  "idempotency_key": "rotate-keys-2025-03",
  "deliver_at": "2025-03-18T09:00",
  "timezone": "Europe/Brussels",
  "message": "Did you rotate the API keys?",
  "recipients": ["alice@example.com", "bob@example.com"],
  "confirmation_mode": "first_response",
  "escalation_rules": {
    "after_minutes": 120,
    "notify": ["cto@example.com"]
  },
  "max_snoozes": 3,
  "token_expires_after_days": 7
}
```

**Response**
```json
{
  "id": "act_456",
  "resolution_status": "resolved",
  "execute_at_utc": "2025-03-18T08:00:00Z"
}
```

---

### 18.4 Respond to Reminder (Human Click)

**POST** `/v1/respond`

```json
{
  "token": "signed_token",
  "action": "snooze",
  "timezone": "Europe/Paris"
}
```

**Response (success)**
```json
{
  "status": "ok",
  "new_execution_time": "2025-03-19T08:00:00Z",
  "message": "Snoozed until Wed, 19 Mar · 09:00 (Europe/Paris)"
}
```

**Response (expired token)**
```json
{
  "status": "error",
  "error": "token_expired",
  "message": "This link has expired. Please log in to reschedule."
}
```

---

## 19. Database Schema (Core Tables)

### 19.1 scheduled_actions

```sql
CREATE TABLE scheduled_actions (
  id UUID PRIMARY KEY,

  -- Multi-tenancy
  owner_user_id UUID NOT NULL,
  owner_team_id UUID,

  -- Type & intent
  type TEXT NOT NULL, -- http | reminder
  intent_type TEXT NOT NULL, -- absolute | wall_clock
  intent_payload JSONB NOT NULL,
  timezone TEXT,

  -- Lifecycle
  resolution_status TEXT NOT NULL,
  execute_at_utc TIMESTAMPTZ,
  executed_at_utc TIMESTAMPTZ,
  failure_reason TEXT,

  -- HTTP retry & idempotency
  idempotency_key TEXT,
  attempt_count INT DEFAULT 0,
  max_attempts INT NOT NULL DEFAULT 1,
  last_attempt_at TIMESTAMPTZ,
  next_retry_at TIMESTAMPTZ,

  -- Reminder behavior
  confirmation_mode TEXT, -- first_response | all_required
  escalation_rules JSONB,
  snooze_count INT DEFAULT 0,
  max_snoozes INT DEFAULT 5,
  token_expires_at TIMESTAMPTZ,

  created_at TIMESTAMPTZ DEFAULT now(),

  -- Constraints
  CONSTRAINT unique_idempotency UNIQUE (owner_user_id, idempotency_key)
);
```

---

### 19.2 delivery_attempts

```sql
CREATE TABLE delivery_attempts (
  id UUID PRIMARY KEY,
  action_id UUID NOT NULL REFERENCES scheduled_actions(id) ON DELETE CASCADE,
  attempt_number INT NOT NULL,
  status TEXT NOT NULL, -- success | failed
  response_code INT,
  response_body TEXT,
  error_message TEXT,
  created_at TIMESTAMPTZ DEFAULT now()
);
```

---

### 19.3 reminder_events

```sql
CREATE TABLE reminder_events (
  id UUID PRIMARY KEY,
  reminder_id UUID NOT NULL REFERENCES scheduled_actions(id) ON DELETE CASCADE,
  event_type TEXT NOT NULL, -- sent | snoozed | confirmed | declined | escalated | expired
  actor_email TEXT,
  captured_timezone TEXT,
  created_at TIMESTAMPTZ DEFAULT now()
);
```

---

### 19.4 reminder_recipients

```sql
CREATE TABLE reminder_recipients (
  id UUID PRIMARY KEY,
  action_id UUID NOT NULL REFERENCES scheduled_actions(id) ON DELETE CASCADE,
  email TEXT NOT NULL,
  status TEXT DEFAULT 'pending', -- pending | confirmed | declined | snoozed
  responded_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT now(),

  CONSTRAINT unique_recipient UNIQUE (action_id, email)
);
```

---

### 19.5 Recommended Indexes

```sql
-- Dispatcher query: find actions ready to execute
CREATE INDEX idx_dispatch_queue 
ON scheduled_actions (resolution_status, execute_at_utc, next_retry_at)
WHERE resolution_status = 'resolved';

-- Dashboard queries: user's actions
CREATE INDEX idx_user_actions 
ON scheduled_actions (owner_user_id, resolution_status, created_at DESC);

-- Team dashboard queries
CREATE INDEX idx_team_actions 
ON scheduled_actions (owner_team_id, resolution_status, created_at DESC)
WHERE owner_team_id IS NOT NULL;

-- Idempotency lookups
CREATE INDEX idx_idempotency 
ON scheduled_actions (owner_user_id, idempotency_key)
WHERE idempotency_key IS NOT NULL;

-- Reminder recipient lookups
CREATE INDEX idx_recipient_status 
ON reminder_recipients (action_id, status);
```

---

## 20. Dispatcher & Resolver Flow

### 20.1 Resolution Phase (Intent → UTC)

```
[ New Action Created ]
        |
        v
[ resolution_status = pending_resolution ]
        |
        v
[ Resolver Job ]
        |
        +--> resolve intent in timezone
        |
        +--> compute execute_at_utc
        |
        v
[ resolution_status = resolved ]
```

---

### 20.2 Dispatch Phase (Execution)

```
[ Dispatcher Tick (every minute) ]
        |
        v
SELECT * FROM scheduled_actions
WHERE resolution_status = 'resolved'
AND (
  execute_at_utc <= now()
  OR next_retry_at <= now()
)
FOR UPDATE SKIP LOCKED
        |
        v
[ Enqueue Delivery Job ]
        |
        v
[ Execute HTTP or Reminder ]
        |
        +--> success -> mark executed
        |
        +--> failure -> retry or mark failed
```

---

## 21. Guarantees & Invariants

- Actions are **executed at most once successfully**
- HTTP actions may be **attempted multiple times** according to retry policy
- Unresolved intents never execute
- Expired tokens are rejected with a clear error
- Reminder escalation only applies in `awaiting_response`
- All state transitions are explicit and auditable

---

## 22. Closing Note

At this point, CallMeLater is fully specified as:
- a clear scheduling primitive
- a safe human-in-the-loop system
- a product that can be built incrementally without rewrites

This spec is sufficient to start implementation immediately.
