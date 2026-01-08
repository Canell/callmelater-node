# CallMeLater: Scheduler & Queue Architecture

This document explains how Laravel's scheduler and queue system work together in CallMeLater to execute scheduled actions (HTTP calls and reminders).

---

## Table of Contents

1. [Overview: Two Separate Systems](#overview-two-separate-systems)
2. [Complete Flow Diagram](#complete-flow-diagram)
3. [The Action Lifecycle](#the-action-lifecycle)
4. [Command Reference](#command-reference)
5. [Jobs Reference](#jobs-reference)
6. [Database Tables](#database-tables)
7. [Redis Usage](#redis-usage)
8. [Debugging Guide](#debugging-guide)

---

## Overview: Two Separate Systems

CallMeLater uses two separate but interconnected systems:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           SCHEDULER (Cron)                                   │
│                         php artisan schedule:run                             │
│                                                                              │
│  Runs every minute via cron. Checks what's due NOW and pushes jobs to queue │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      │ Pushes jobs to Redis queue
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              REDIS QUEUE                                     │
│                         (queues:default, queues:high)                        │
│                                                                              │
│  A waiting list of jobs. Jobs sit here until a worker picks them up.        │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      │ Workers pull jobs from Redis
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           QUEUE WORKER                                       │
│                         php artisan queue:work                               │
│                                                                              │
│  Long-running process. Pulls jobs from Redis and executes them one by one.  │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Key Distinction

| System | Command | Purpose | Runs As |
|--------|---------|---------|---------|
| Scheduler | `php artisan schedule:run` | Check what's due, push jobs to queue | Cron (every minute) |
| Queue Worker | `php artisan queue:work` | Execute jobs from queue | Supervisor (daemon) |

---

## Complete Flow Diagram

### Step-by-Step: From Action Creation to Execution

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│ STEP 1: User Creates Action via API                                              │
│ POST /api/v1/actions                                                             │
└──────────────────────────────────────────────────────────────────────────────────┘
                                         │
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│ ActionController::store()                                                        │
│                                                                                  │
│ 1. Creates ScheduledAction with status = "pending_resolution"                   │
│ 2. Dispatches ResolveIntentJob → pushed to REDIS queue                          │
│                                                                                  │
│ DB Table: scheduled_actions (new row inserted)                                   │
└──────────────────────────────────────────────────────────────────────────────────┘
                                         │
                                         │ Job pushed to Redis
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│ REDIS QUEUE: "queues:default"                                                    │
│                                                                                  │
│ Job payload: { "job": "ResolveIntentJob", "data": { "action_id": "uuid..." } }  │
└──────────────────────────────────────────────────────────────────────────────────┘
                                         │
                                         │ queue:work pulls job
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│ STEP 2: Queue Worker Executes ResolveIntentJob                                   │
│ php artisan queue:work                                                           │
│                                                                                  │
│ ResolveIntentJob::handle():                                                      │
│   - Parses intent (preset: "tomorrow", delay: "2h", etc.)                       │
│   - Calculates execute_at_utc                                                    │
│   - Updates: status = "resolved", execute_at_utc = "2025-01-08 09:00:00"        │
│                                                                                  │
│ DB Table: scheduled_actions (row updated)                                        │
└──────────────────────────────────────────────────────────────────────────────────┘
                                         │
                                         │ Action now waits in DB
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│ scheduled_actions table                                                          │
│ ┌─────────────────────────────────────────────────────────────────────────────┐ │
│ │ id       │ resolution_status │ execute_at_utc      │ type     │            │ │
│ │ abc-123  │ resolved          │ 2025-01-08 09:00:00 │ reminder │            │ │
│ └─────────────────────────────────────────────────────────────────────────────┘ │
│                                                                                  │
│ The action sits here, waiting until execute_at_utc is reached                    │
└──────────────────────────────────────────────────────────────────────────────────┘
                                         │
                                         │ Time passes...
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│ STEP 3: Cron Runs schedule:run Every Minute                                      │
│ * * * * * php artisan schedule:run                                               │
│                                                                                  │
│ What it does:                                                                    │
│   1. Checks routes/console.php for due tasks                                     │
│   2. DispatcherJob is due (everyMinute) → pushes to Redis queue                 │
│   3. check-expired-reminders is due (every 5 min) → runs inline                 │
│                                                                                  │
│ Note: Schedule::job() pushes to queue, Schedule::command() runs directly         │
└──────────────────────────────────────────────────────────────────────────────────┘
                                         │
                                         │ DispatcherJob pushed to Redis
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│ STEP 4: Queue Worker Executes DispatcherJob                                      │
│                                                                                  │
│ DispatcherJob::handle():                                                         │
│   1. Query: SELECT * FROM scheduled_actions                                      │
│      WHERE resolution_status = 'resolved'                                        │
│      AND (execute_at_utc <= NOW() OR next_retry_at <= NOW())                    │
│      FOR UPDATE SKIP LOCKED                                                      │
│                                                                                  │
│   2. For each action found:                                                      │
│      - Update status = "executing" (prevents re-selection)                       │
│      - Dispatch DeliverHttpAction or DeliverReminder to queue                   │
│                                                                                  │
│ DB Table: scheduled_actions (status updated to "executing")                      │
└──────────────────────────────────────────────────────────────────────────────────┘
                                         │
                                         │ DeliverReminder pushed to Redis
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│ STEP 5: Queue Worker Executes DeliverReminder (or DeliverHttpAction)             │
│                                                                                  │
│ DeliverReminder::handle():                                                       │
│   1. Creates ReminderRecipient records                                           │
│   2. Sends emails via Postmark / SMS via Twilio                                  │
│   3. Creates ReminderEvent record (type: "sent")                                │
│   4. Updates action: status = "awaiting_response"                               │
│                                                                                  │
│ DeliverHttpAction::handle():                                                     │
│   1. Makes HTTP request to target URL                                            │
│   2. Records DeliveryAttempt                                                     │
│   3. On success: status = "executed"                                             │
│   4. On failure: schedule retry or status = "failed"                            │
│                                                                                  │
│ DB Tables:                                                                       │
│   - reminder_recipients (new rows)                                               │
│   - reminder_events (new row)                                                    │
│   - delivery_attempts (new row for HTTP)                                         │
│   - scheduled_actions (status updated)                                           │
└──────────────────────────────────────────────────────────────────────────────────┘
```

---

## The Action Lifecycle

### State Machine

```
User creates action
       │
       ▼
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│ pending_        │────▶│    resolved     │────▶│   executing     │
│ resolution      │     │                 │     │                 │
└─────────────────┘     └─────────────────┘     └─────────────────┘
  ResolveIntentJob        (waiting in DB)          DispatcherJob
  calculates time         for execute_at_utc       picks it up
       │                                                 │
       └── queue:work                                    └── queue:work
                                                               │
                                                               ▼
                                              ┌─────────────────────────────┐
                                              │  DeliverHttpAction    OR    │
                                              │  DeliverReminder            │
                                              └─────────────────────────────┘
                                                               │
                      ┌────────────────────────────────────────┼────────────────┐
                      ▼                                        ▼                ▼
              ┌──────────────┐                        ┌──────────────┐  ┌──────────────┐
              │   executed   │                        │awaiting_     │  │   failed     │
              │              │                        │response      │  │              │
              └──────────────┘                        └──────────────┘  └──────────────┘
                 (HTTP done)                           (Reminder sent,    (After max
                                                        waiting for       retries)
                                                        confirm/decline)
```

### Status Descriptions

| Status | Meaning | Next Step |
|--------|---------|-----------|
| `pending_resolution` | Just created, intent not yet parsed | `ResolveIntentJob` will process |
| `resolved` | Ready to execute, waiting for scheduled time | `DispatcherJob` checks every minute |
| `executing` | Currently being processed | Delivery job running |
| `executed` | Successfully completed | Terminal state |
| `awaiting_response` | Reminder sent, waiting for user response | User clicks confirm/decline |
| `failed` | Failed after all retries | Terminal state |
| `cancelled` | Cancelled by user | Terminal state |

---

## Command Reference

### `php artisan schedule:run`

**Purpose**: Check what scheduled tasks are due RIGHT NOW and execute/dispatch them.

**How it works**:
1. Reads `routes/console.php` (your schedule definitions)
2. Checks current time against each task's schedule
3. For `Schedule::job()` → pushes the job to Redis queue
4. For `Schedule::command()` → runs the command inline (in the same process)

**Scheduled Tasks in CallMeLater**:

| Task | Frequency | Behavior |
|------|-----------|----------|
| `DispatcherJob` | Every minute | Pushed to Redis queue |
| `app:check-expired-reminders` | Every 5 min | Runs inline (not queued) |
| `app:recover-stuck-executing-actions` | Every 5 min | Runs inline (not queued) |

**Production cron entry**:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

### `php artisan queue:work`

**Purpose**: Long-running worker that pulls jobs from Redis and executes them.

**How it works**:
1. Connects to Redis (`QUEUE_CONNECTION=redis` in `.env`)
2. Listens on queue(s) like `queues:default`
3. When a job appears: pulls it, deserializes, calls `handle()` method
4. On success: removes job from Redis
5. On failure: retries based on `$tries` and `$backoff`, or moves to `failed_jobs` table

**Common options**:
```bash
# Basic worker
php artisan queue:work

# Verbose output (see job names)
php artisan queue:work --verbose

# Process only one job then exit
php artisan queue:work --once

# Specify queue priority
php artisan queue:work --queue=high,default

# Memory limit (restart if exceeded)
php artisan queue:work --memory=128

# Sleep time when no jobs available
php artisan queue:work --sleep=3
```

---

### `php artisan schedule:work`

**Purpose**: Development alternative to cron. Runs the scheduler continuously.

```bash
# Runs schedule:run every minute automatically
php artisan schedule:work
```

---

## Jobs Reference

### ResolveIntentJob

| Property | Value |
|----------|-------|
| **File** | `app/Jobs/ResolveIntentJob.php` |
| **Triggered by** | `ActionController::store()` |
| **Purpose** | Parse intent (preset/delay) into UTC datetime |
| **Retries** | 3 times with 30s backoff |

**Input**: Action with `pending_resolution` status
**Output**: Action with `resolved` status and `execute_at_utc` set

```
Intent Examples:
  preset: "tomorrow"     →  execute_at_utc: 2025-01-08 09:00:00
  preset: "next_monday"  →  execute_at_utc: 2025-01-13 09:00:00
  delay: "2h"            →  execute_at_utc: NOW + 2 hours
  delay: "30m"           →  execute_at_utc: NOW + 30 minutes
```

---

### DispatcherJob

| Property | Value |
|----------|-------|
| **File** | `app/Jobs/DispatcherJob.php` |
| **Triggered by** | Scheduler (every minute) |
| **Purpose** | Find due actions and dispatch delivery jobs |
| **Retries** | 1 (no retries) |

**Query logic**:
```sql
SELECT * FROM scheduled_actions
WHERE resolution_status = 'resolved'
  AND (execute_at_utc <= NOW() OR next_retry_at <= NOW())
ORDER BY execute_at_utc
LIMIT 100
FOR UPDATE SKIP LOCKED
```

**Process**:
1. Lock rows to prevent concurrent dispatch
2. Change status to `executing`
3. Dispatch `DeliverHttpAction` or `DeliverReminder`

---

### DeliverHttpAction

| Property | Value |
|----------|-------|
| **File** | `app/Jobs/DeliverHttpAction.php` |
| **Triggered by** | `DispatcherJob` |
| **Purpose** | Make HTTP request to target URL |
| **Retries** | 3 times with 60s backoff |

**Process**:
1. Verify action is still in `executing` state
2. Make HTTP request with configured method/headers/body
3. Record attempt in `delivery_attempts` table
4. On success: mark `executed`
5. On failure: schedule retry or mark `failed`

---

### DeliverReminder

| Property | Value |
|----------|-------|
| **File** | `app/Jobs/DeliverReminder.php` |
| **Triggered by** | `DispatcherJob` |
| **Purpose** | Send reminder emails/SMS |
| **Retries** | 3 times with 60s backoff |

**Process**:
1. Verify action is still in `executing` state
2. Create `ReminderRecipient` records (using `firstOrCreate` for idempotency)
3. Check consent status for each recipient
4. Send emails (Postmark) or SMS (Twilio)
5. Record event in `reminder_events`
6. Mark action as `awaiting_response`

---

## Database Tables

### Core Tables

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `scheduled_actions` | Core action storage | `id`, `resolution_status`, `execute_at_utc`, `type`, `owner_user_id` |
| `delivery_attempts` | HTTP call audit log | `action_id`, `attempt_number`, `status_code`, `response_body` |
| `reminder_recipients` | Per-recipient tracking | `action_id`, `email`, `status`, `response_token` |
| `reminder_events` | Reminder timeline | `reminder_id`, `event_type`, `notes`, `created_at` |
| `notification_consents` | Email opt-in tracking | `email`, `status`, `consent_token` |

### Laravel Queue Tables

| Table | Purpose | Notes |
|-------|---------|-------|
| `jobs` | Pending jobs | Only used if `QUEUE_CONNECTION=database` |
| `failed_jobs` | Failed jobs after all retries | Always used for debugging |

---

## Redis Usage

### Configuration

```env
# .env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

### Redis Keys Created by Laravel

```
queues:default           ← Main job queue (list)
queues:default:reserved  ← Jobs currently being processed
queues:default:delayed   ← Jobs scheduled for future execution
queues:default:notify    ← Notification for blocking pop
laravel_database_*       ← Cache entries (if using Redis cache)
```

### Inspecting Redis

```bash
# Connect to Redis CLI
redis-cli

# See all Laravel queue keys
KEYS *queue*

# See pending jobs in default queue
LRANGE queues:default 0 -1

# Count pending jobs
LLEN queues:default

# See delayed jobs (sorted set)
ZRANGE queues:default:delayed 0 -1 WITHSCORES
```

---

## Debugging Guide

### Option 1: Run Both Systems Locally

```bash
# Terminal 1: Run scheduler continuously (like cron)
php artisan schedule:work

# Terminal 2: Process queue jobs
php artisan queue:work --verbose
```

### Option 2: Trigger Manually (One-Time)

```bash
# Run scheduler once (as if cron triggered it)
php artisan schedule:run

# Process ONE job from queue
php artisan queue:work --once --verbose

# Process all pending jobs then exit
php artisan queue:work --stop-when-empty --verbose
```

### Option 3: Bypass Queue (Sync Mode)

For immediate debugging, temporarily change `.env`:

```env
QUEUE_CONNECTION=sync
```

Now all jobs run immediately in the same process - no Redis needed!

### Option 4: Tail the Logs

```bash
tail -f storage/logs/laravel.log
```

**Key log messages to look for**:
```
[INFO] Intent resolved successfully
[INFO] Dispatcher completed {"dispatched": 5}
[DEBUG] Action dispatched {"action_id": "...", "type": "reminder"}
[INFO] Reminder delivered {"sent": 2, "awaiting_consent": 1}
[ERROR] Failed to send reminder email
```

### Option 5: Check Failed Jobs

```bash
# List failed jobs
php artisan queue:failed

# Retry a specific failed job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Clear all failed jobs
php artisan queue:flush
```

### Option 6: Tinker with Actions

```bash
php artisan tinker
```

```php
// Find pending actions
App\Models\ScheduledAction::where('resolution_status', 'resolved')->get();

// Check action status
$action = App\Models\ScheduledAction::find('uuid-here');
$action->resolution_status;
$action->execute_at_utc;

// Manually dispatch an action
App\Jobs\DeliverReminder::dispatch($action);
```

---

## Production Setup

### Cron Entry

```bash
# /etc/crontab or `crontab -e`
* * * * * cd /var/www/callmelater && php artisan schedule:run >> /dev/null 2>&1
```

### Supervisor Configuration

```ini
# /etc/supervisor/conf.d/callmelater-worker.conf
[program:callmelater-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/callmelater/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/callmelater/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start callmelater-worker:*
```

---

## Quick Reference Card

| Task | Command |
|------|---------|
| Start scheduler (dev) | `php artisan schedule:work` |
| Start queue worker | `php artisan queue:work --verbose` |
| Run scheduler once | `php artisan schedule:run` |
| Process one job | `php artisan queue:work --once` |
| Check failed jobs | `php artisan queue:failed` |
| Retry failed job | `php artisan queue:retry <id>` |
| Clear all jobs | `php artisan queue:clear` |
| Check Redis queue | `redis-cli LLEN queues:default` |
| Enable sync mode | Set `QUEUE_CONNECTION=sync` in .env |
