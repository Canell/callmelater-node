# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CallMeLater is a developer-first SaaS for scheduling durable, long-term actions. It combines two primitives:
1. **Deferred HTTP calls** - Schedule HTTP requests to execute later with retry policies
2. **Interactive human reminders** - Send reminders with Yes/No/Snooze responses and escalation

## Tech Stack

- **Backend**: Laravel (PHP), MySQL, Redis
- **Frontend**: Vue.js, Bootstrap
- **Notifications**: Email (SES/Mailgun/Postmark), SMS (Twilio for paid tiers)

## Expected Commands

```bash
# Backend
composer install
php artisan migrate
php artisan serve
php artisan test
./vendor/bin/phpstan analyze
./vendor/bin/php-cs-fixer fix

# Frontend
npm install
npm run dev
npm run build
npm run test
npm run lint
```

## Architecture

### Core Concepts

**Resolution Status Lifecycle** - All actions flow through explicit states:
- `pending_resolution` → `resolved` → `executed` (or `awaiting_response` for reminders)
- Other terminal states: `cancelled`, `expired`, `failed`

**Two-Phase Execution**:
1. **Resolver**: Converts wall-clock intent (e.g., "next_monday") to UTC timestamp
2. **Dispatcher**: Minute-based job selecting due actions via `SELECT ... FOR UPDATE SKIP LOCKED`

### Key Components

- **Intent Resolver** (`ResolveIntentJob`): Handles timezone-aware scheduling with presets (`tomorrow`, `next_week`, `next_monday`) and relative delays
- **Dispatcher** (`DispatcherJob`): Runs every minute, finds `resolved` actions where `execute_at_utc <= now()`
- **Delivery Workers**: Idempotent HTTP execution with exponential backoff; reminder delivery with token-based responses

### Database Schema

Primary tables:
- `scheduled_actions` - Core table with resolution status, retry config, reminder behavior
- `delivery_attempts` - HTTP attempt audit log
- `reminder_events` - Reminder interaction timeline (sent, snoozed, confirmed, declined, expired)
- `reminder_recipients` - Per-recipient status for team reminders

Key index: `idx_dispatch_queue` on `(resolution_status, execute_at_utc, next_retry_at)` for dispatcher queries

### API Design

- Bearer token auth (`sk_live_...`)
- Single endpoint `POST /v1/actions` for both HTTP calls and reminders (distinguished by `type` field)
- `POST /v1/respond` for signed token-based reminder responses

## Critical Invariants

- Actions execute at most once successfully
- Unresolved intents (`pending_resolution`) never execute
- Token expiry default: 7 days after reminder sent
- Max snoozes configurable per action (default: 5)
- Confirmation modes for team reminders: `first_response` or `all_required`

## Testing Focus Areas

- DST transitions (spring forward/back) with timezone-aware scheduling
- Snooze edge cases (near midnight, on target day like "next Monday" on Monday)
- Token expiry boundary conditions
- Idempotent HTTP delivery (no duplicate successful executions)
