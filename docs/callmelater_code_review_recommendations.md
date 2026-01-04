# CallMeLater — Code Review Notes (Updated)

## Scope
This review covers:
- API routes
- Core dispatcher / executor logic
- Database migrations
- ScheduledAction (core model)

Focus: correctness, concurrency safety, and long-term maintainability.

---

## What’s Solid

- API surface is clean and consistent with the product spec
- Public reminder response endpoint is unauthenticated but rate-limited (correct)
- Admin routes are properly gated
- Dispatcher logic is incremental and restart-safe
- Core state is modeled explicitly with constants (good foundation)

---

## Critical Improvements (High Priority)

### 1. Enforce row-level locking in dispatcher queries
All “due action” selections must use row-level locks (e.g. `FOR UPDATE SKIP LOCKED`).

This prevents:
- double execution
- race conditions across workers
- retry overlap bugs

This is non-negotiable for a reliability product.

---

### 2. Make state transitions explicit and enforced
The ScheduledAction model must *own* its state machine.

Avoid setting `status` directly.

Introduce explicit transitions:
- `markAsExecuting()`
- `markAsAwaitingResponse()`
- `markAsResolved()`
- `markAsSucceeded()`
- `markAsFailed(reason)`
- `cancel()`

Each transition must:
- validate the current state
- throw on invalid transitions
- persist atomically

This prevents ghost states and silent corruption.

---

### 3. Separate “resolution state” from “execution outcome”
One `status` field currently represents multiple concepts.

Logically distinguish:
- **Resolution state** (pending, awaiting response, resolved)
- **Execution outcome** (executing, success, failed, cancelled)

This does not require two DB columns immediately, but *does* require:
- clear helper methods (`isExecutable()`, `isAwaitingResponse()`, etc.)
- consistent semantics across jobs and controllers

This dramatically simplifies dispatcher logic and admin reporting.

---

### 4. Make cancellation terminal and explicit
Cancellation rules must live in the model.

Add:
- `canBeCancelled()`
- `cancel()`

Decide and enforce whether cancellation is terminal.
Callers must not guess.

---

### 5. Centralize retry logic in the model
Retry behavior must not be spread across jobs.

The model should answer:
- `hasAttemptsLeft()`
- `shouldRetry()`
- `scheduleNextRetry()`

Jobs execute decisions — they do not invent them.

This guarantees consistent retry semantics.

---

## Schema & Data Integrity

### 6. Enforce database-level foreign keys
Add explicit FKs for:
- scheduled_actions → users
- reminder_events → scheduled_actions
- reminder_recipients → scheduled_actions

Do not rely solely on application logic.
Silent orphan data will break admin stats and audits.

---

### 7. Explicit ownership & tenancy
All core tables must include:
- `user_id`
- `team_id` (if applicable)

This is required for:
- quotas
- billing
- admin metrics
- future team features

---

## API Consistency

### 8. Normalize API versioning
All public endpoints should live under `/v1/*`.

Admin endpoints remain internal and unversioned.

This avoids future breaking changes.

---

## Model Design Guidance

The ScheduledAction model should be:
> “The law. Everything else obeys.”

It must:
- define allowed states
- define valid transitions
- define retry and cancellation rules

Controllers and jobs should *ask the model*, not decide.

---

## Summary

The foundation is strong and well thought out.
The main remaining risks are:
- implicit state transitions
- concurrency edge cases
- scattered retry logic

Fixing these now will prevent the hardest bugs later.

---

## Recommended Next Review

Yes — the dispatcher job you just uploaded **is exactly the next file to review**.

Specifically, the code responsible for:
- selecting due actions
- locking rows
- transitioning state before execution

That file is where correctness either becomes rock-solid — or subtly broken.
