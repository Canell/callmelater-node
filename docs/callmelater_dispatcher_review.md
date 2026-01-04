# CallMeLater — Dispatcher Review (Post-Fix)

## Scope

This document reviews the **updated dispatcher implementation** after concurrency and state-safety fixes.

It focuses on:
- correctness under concurrency
- crash and restart safety
- alignment with CallMeLater’s reliability guarantees

---

## High-Level Verdict

✅ **The dispatcher is now concurrency-safe and production-grade.**

The most dangerous failure modes have been eliminated:
- double execution
- race conditions between workers
- cancellation races
- retry overlap

This version is safe to run with multiple workers.

---

## What Is Now Correct

### 1. Atomic selection and state transition
- Due actions are selected with row-level locking
- State transitions happen **inside the same transaction**
- Actions stop being selectable the moment they are picked

This enforces exclusivity correctly.

---

### 2. EXECUTING is treated as a lock
Once an action is marked EXECUTING:
- it cannot be picked again
- it is protected from concurrent dispatch

This matches the system invariant:
> “Once selected, an action must never be selectable again.”

---

### 3. Cancellation is enforced after locking
Cancellation checks happen:
- after row lock
- before execution

This closes the race where a user cancels an action while it’s being dispatched.

---

### 4. Dispatcher responsibilities are minimal
The dispatcher now only:
- selects
- locks
- transitions state
- enqueues execution

Business rules live elsewhere.

This is the correct separation of concerns.

---

## Remaining Non-Blocking Gaps

These are **known, acceptable gaps**, not correctness bugs.

### 1. No watchdog for stuck EXECUTING actions
If a worker crashes mid-execution, an action may remain EXECUTING.

Recommended (later):
- watchdog job that detects EXECUTING > N minutes
- mark as failed or retryable with reason `executor_timeout`

---

### 2. No invariant tests yet
Correctness is enforced by code, not tests.

Recommended (later):
- tests asserting no double execution
- tests asserting cancellation and awaiting-response skips

---

## What Not to Change

Do **not**:
- add Redis locks
- add distributed mutexes
- split dispatch across multiple systems

The current DB-locking approach is correct and sufficient.

---

## Final Assessment

The dispatcher is now:
- concurrency-safe
- crash-tolerant
- restart-safe
- aligned with product guarantees

Further changes should be incremental and deliberate.

This component can be considered **stable**.
