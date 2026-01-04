# CallMeLater — Dispatcher Job Review & Fix Plan

## Scope

This document is a **deep technical review** of the dispatcher job responsible for:
- selecting due actions
- transitioning their state
- enqueuing execution

This job is the **core reliability component** of CallMeLater.

---

## Dispatcher Responsibilities (Invariants)

The dispatcher **must guarantee** the following at all times:

1. No action is executed twice concurrently
2. No action is lost on crash or restart
3. State transitions are explicit and monotonic
4. Retries cannot race with first executions
5. Cancelled actions are never executed
6. Actions awaiting human response are never executed
7. Dispatcher is safe to run with multiple workers

If any of these are violated, failures will be:
- rare
- silent
- extremely hard to debug

---

## What’s Working Well

- Selection and execution are separated (good design)
- Dispatcher is restart-tolerant in spirit
- Explicit status values exist (good foundation)
- Logic is incremental, not “scan everything”

This is a solid base.

---

## Critical Issues Identified

### 1. Missing Row-Level Locking (CRITICAL)

Currently, due actions can be selected by multiple workers at the same time.

This creates race conditions such as:
- double execution
- duplicate retries
- inconsistent logs

#### Why this happens
Selection and state transition are not atomic.

---

### 2. State Transition Happens Too Late

Actions remain in a runnable state while logic is executing.

This creates a race window where:
- another dispatcher tick
- another worker

can pick the same action.

**Rule:**  
> The moment an action is selected, it must stop being selectable.

---

### 3. Cancellation Is Not Enforced Strongly Enough

An action can be:
- selected
- then cancelled
- but still executed

Unless cancellation is checked *after locking* and *before execution*, this race exists.

---

### 4. Retry Logic Is Mixed Into Dispatch Logic

Retry timing, limits, and decisions are spread across dispatcher code.

This leads to:
- inconsistent behavior
- fragile changes
- hard-to-debug edge cases

Retry policy must live in the model.

---

### 5. No Explicit State Invariants

There is no single authority enforcing:
- valid transitions
- terminal states
- illegal state moves

This allows subtle bugs to accumulate over time.

---

## Required Fixes (Concrete & Incremental)

### Fix 1 — Atomic Selection + Early Transition

The dispatcher must:

1. Select due actions **with row-level locks**
2. Transition them to EXECUTING **inside the same transaction**
3. Only then enqueue execution

#### Conceptual flow

```php
DB::transaction(function () {
    $actions = ScheduledAction::due()
        ->lockForUpdateSkipLocked()
        ->limit($batchSize)
        ->get();

    foreach ($actions as $action) {
        if (!$action->canBeExecuted()) {
            continue;
        }

        $action->markAsExecuting();
    }
});
```

---

### Fix 2 — Make EXECUTING a Hard Lock

Once an action is EXECUTING:
- it must never be selectable again
- until it transitions to a terminal or retry state

EXECUTING is not “informational” — it is a lock.

---

### Fix 3 — Push All State Law Into the Model

The model must define and enforce all rules.

Required methods:

```php
canBeExecuted()
markAsExecuting()
markAsSucceeded()
markAsFailed(reason)
shouldRetry()
scheduleNextRetry()
cancel()
```

Jobs must **ask the model**, not decide.

---

### Fix 4 — Enforce Cancellation After Locking

After row lock, before execution:

```php
if (!$action->canBeExecuted()) {
    continue;
}
```

Cancellation must be terminal and enforced here.

---

### Fix 5 — Centralize Retry Policy

Retry decisions must live in one place.

Dispatcher logic should never contain:
- backoff math
- attempt limits
- terminal retry decisions

Those belong to the model.

---

## Production Safety Checklist

Before considering this dispatcher production-safe:

- [ ] Uses row-level locking (`SKIP LOCKED`)
- [ ] Transitions state before execution
- [ ] Enforces cancellation post-lock
- [ ] Treats EXECUTING as a lock
- [ ] Centralizes retry rules
- [ ] Handles multi-worker concurrency safely

---

## Final Verdict

The dispatcher architecture is **conceptually correct**, but **not yet safe under concurrency**.

The missing piece is **atomic selection + early state transition**.

Once fixed:
- crashes become boring
- restarts are safe
- retries are predictable
- reliability promises hold

---

## Recommended Next Reviews

1. HTTP execution & retry job
2. Reminder response handler
3. Watchdog for stuck EXECUTING actions
4. Invariant tests for state transitions

This document should be kept as a reference while refactoring the dispatcher.
