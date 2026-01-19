# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Current Progress

- **Phase 1-2**: Complete - Laravel 12 project setup, Vue 3 + Bootstrap 5 frontend, database schema
- **Phase 3**: Complete - Fortify auth (login/register/password reset), Sanctum API tokens (`sk_live_` prefix)
- **Phase 4**: Complete - Core engine (ActionService, IntentResolver, ResolveIntentJob, DispatcherJob)
- **Phase 5**: Complete - HTTP Call Execution (DeliverHttpAction with retry logic, webhook signing)
- **Phase 6**: Complete - Postmark email, Brevo SMS, response handling
- **Phase 7**: Complete - Public API (ActionController, ResponseController, Form Requests, Resources)
- **Phase 8**: Complete (Basic) - Dashboard, CreateAction, ActionDetail pages
- **Phase 9-10**: Next - Security hardening, production deployment

## Project Overview

CallMeLater is a developer-first SaaS for scheduling durable, long-term actions. It combines two primitives:
1. **Deferred HTTP calls** - Schedule HTTP requests to execute later with retry policies
2. **Interactive human reminders** - Send reminders with Yes/No/Snooze responses and escalation

## Tech Stack

- **Backend**: Laravel 12, MySQL, Redis
- **Frontend**: Vue 3, Vue Router, Bootstrap 5
- **Auth**: Laravel Fortify (web), Laravel Sanctum (API tokens)
- **Notifications**: Email (SES/Mailgun/Postmark), SMS (Brevo for paid tiers)

## Commands

```bash
# Quick start
make install          # Install composer + npm dependencies
make dev              # Start Laravel + Vite dev servers

# Individual commands
make serve            # Laravel server only
make test             # Run PHPUnit tests
make lint             # Run PHPStan + Pint checks
make fix              # Fix code style with Pint
make migrate          # Run migrations
make fresh            # Fresh migrate with seeders
make queue            # Start queue worker
make schedule         # Run scheduler

# Or directly
php artisan test --filter=TestName   # Single test
./vendor/bin/phpstan analyse         # Static analysis
./vendor/bin/pint                    # Code style fixer
npm run build                        # Production build
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
