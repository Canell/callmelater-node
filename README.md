# CallMeLater

A SaaS platform for scheduling durable HTTP calls and interactive human reminders.

## Features

- **Scheduled HTTP Calls** - Fire webhooks at a specific time with retry logic
- **Interactive Reminders** - Send email/SMS reminders with confirm/decline/snooze actions
- **Flexible Scheduling** - Use presets (`tomorrow`, `next_monday`), delays (`2h`, `3d`), or exact times
- **Idempotency** - Prevent duplicate actions with idempotency keys
- **Webhook Signing** - HMAC-SHA256 signatures for secure webhook delivery
- **SSRF Protection** - Blocks requests to private IPs and internal networks

## Requirements

- PHP 8.3+
- PostgreSQL 14+
- Redis 6+
- Node.js 20+

## Installation

```bash
# Clone the repository
git clone <repository-url>
cd callmelater

# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Build frontend
npm run build
```

## Local Development

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite dev server
npm run dev

# Terminal 3: Queue worker
php artisan queue:work

# Terminal 4: Scheduler
php artisan schedule:work
```

Or use synchronous queue processing:
```env
QUEUE_CONNECTION=sync
```

## API Reference

All API endpoints require authentication via Bearer token or session cookie.

### Authentication

```bash
# Get an API token from the dashboard, then:
curl -H "Authorization: Bearer sk_live_..." https://your-domain.com/api/v1/actions
```

### Actions

#### Create Action

```http
POST /api/v1/actions
Content-Type: application/json
```

**HTTP Call Example:**
```json
{
  "type": "http",
  "idempotency_key": "deploy-notification-2025-01",
  "intent": {
    "preset": "tomorrow"
  },
  "http_request": {
    "method": "POST",
    "url": "https://api.example.com/webhook",
    "headers": {
      "X-Custom-Header": "value"
    },
    "body": {
      "event": "deployment_complete"
    }
  },
  "max_attempts": 5,
  "retry_strategy": "exponential"
}
```

**Reminder Example:**
```json
{
  "type": "reminder",
  "idempotency_key": "standup-reminder-2025-01-04",
  "intent": {
    "delay": "2h"
  },
  "reminder_message": "Did you complete the standup?",
  "recipients": [
    "user@example.com",
    "+1234567890"
  ],
  "confirmation_mode": "any",
  "max_snoozes": 3
}
```

**Intent Options:**
| Field | Description | Example |
|-------|-------------|---------|
| `preset` | Named time | `tomorrow`, `next_monday`, `1h`, `3d` |
| `delay` | Relative delay | `30m`, `2h`, `1d` |
| `execute_at` | Exact UTC time | `2025-01-15T14:30:00Z` |
| `timezone` | For presets/delays | `America/New_York` |

**Response:** `201 Created`
```json
{
  "data": {
    "id": "uuid",
    "type": "http",
    "resolution_status": "resolved",
    "execute_at_utc": "2025-01-05T09:00:00Z",
    "created_at": "2025-01-04T10:00:00Z"
  }
}
```

#### List Actions

```http
GET /api/v1/actions?status=resolved&type=http&per_page=25
```

**Query Parameters:**
| Param | Description |
|-------|-------------|
| `status` | Filter by status: `pending_resolution`, `resolved`, `executed`, `failed`, `cancelled` |
| `type` | Filter by type: `http`, `reminder` |
| `per_page` | Results per page (default: 25) |

#### Get Action

```http
GET /api/v1/actions/{id}
```

Returns action details with delivery attempts and reminder events.

#### Cancel Action by ID

```http
DELETE /api/v1/actions/{id}
```

**Response:** `200 OK` or `422` if already executed.

#### Cancel Action by Idempotency Key

```http
DELETE /api/v1/actions
Content-Type: application/json

{
  "idempotency_key": "deploy-notification-2025-01"
}
```

**Response Codes:**
| Status | Meaning |
|--------|---------|
| `200` | Cancelled (or already cancelled) |
| `404` | Not found |
| `409` | Already executed |

### Reminder Responses

Public endpoint for reminder recipients to respond (no auth required):

```http
POST /api/v1/respond
Content-Type: application/json

{
  "token": "response-token-from-email",
  "response": "confirm"
}
```

**Response Options:** `confirm`, `decline`, `snooze`

### API Tokens

```http
GET    /api/tokens          # List tokens
POST   /api/tokens          # Create token
DELETE /api/tokens/{id}     # Revoke token
```

## Webhook Signatures

When `webhook_secret` is set on an action, outgoing requests include:

```http
X-CallMeLater-Signature: sha256=<hmac>
X-CallMeLater-Action-Id: <uuid>
X-CallMeLater-Timestamp: <unix-timestamp>
```

**Verification (PHP example):**
```php
$payload = file_get_contents('php://input');
$expected = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);
$valid = hash_equals($expected, $_SERVER['HTTP_X_CALLMELATER_SIGNATURE']);
```

## Configuration

Key environment variables:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=callmelater

# Queue & Cache
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# Email (Postmark)
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-token

# SMS (Twilio)
TWILIO_SID=your-sid
TWILIO_TOKEN=your-token
TWILIO_FROM=+1234567890

# Security
CML_BLOCK_PRIVATE_IPS=true
CML_RATE_LIMIT_API=100
CML_RATE_LIMIT_CREATE=100
```

See `config/callmelater.php` for all options.

## Rate Limits

| Endpoint | Limit |
|----------|-------|
| API (authenticated) | 100 req/min |
| API (unauthenticated) | 20 req/min |
| Create actions | 100/hour per user |
| Reminder responses | 10 req/min per token |

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for production deployment instructions.

## License

Proprietary - All rights reserved.
