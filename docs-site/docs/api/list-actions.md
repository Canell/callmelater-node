---
sidebar_position: 3
---

# List Actions

Retrieve a paginated list of your actions with optional filtering.

```
GET /api/v1/actions
```

## Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | — | Filter by status |
| `mode` | string | — | Filter by mode (`immediate` or `gated`) |
| `search` | string | — | Search in name and description |
| `coordination_key` | string | — | Filter by coordination key |
| `per_page` | integer | 25 | Results per page (max 100) |
| `page` | integer | 1 | Page number |

### Status Values

- `pending_resolution` - Waiting for time resolution
- `resolved` - Scheduled, waiting for execution time
- `executing` - Currently being processed
- `awaiting_response` - Waiting for human response (gated only)
- `executed` - Successfully completed
- `failed` - Failed after all retries
- `cancelled` - Manually or automatically cancelled
- `expired` - Reminder expired without response

## Examples

### List All Actions

```bash
curl https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..."
```

### Filter by Status

```bash
curl "https://api.callmelater.io/v1/actions?status=failed" \
  -H "Authorization: Bearer sk_live_..."
```

### Filter by Mode

```bash
curl "https://api.callmelater.io/v1/actions?mode=gated" \
  -H "Authorization: Bearer sk_live_..."
```

### Search by Name

```bash
curl "https://api.callmelater.io/v1/actions?search=deployment" \
  -H "Authorization: Bearer sk_live_..."
```

### Filter by Coordination Key

```bash
curl "https://api.callmelater.io/v1/actions?coordination_key=deploy:api-service" \
  -H "Authorization: Bearer sk_live_..."
```

### Combined Filters

```bash
curl "https://api.callmelater.io/v1/actions?status=resolved&mode=immediate&per_page=50" \
  -H "Authorization: Bearer sk_live_..."
```

## Response

```json
{
  "data": [
    {
      "id": "01234567-89ab-cdef-0123-456789abcdef",
      "name": "Trial expiration webhook",
      "description": null,
      "mode": "immediate",
      "status": "resolved",
      "timezone": "America/New_York",
      "execute_at": "2025-01-08T09:00:00Z",
      "idempotency_key": "trial-end-user-42",
      "coordination_keys": ["user:42"],
      "attempt_count": 0,
      "max_attempts": 5,
      "retry_strategy": "exponential",
      "created_at": "2025-01-05T10:30:00Z",
      "updated_at": "2025-01-05T10:30:00Z"
    },
    {
      "id": "fedcba98-7654-3210-fedc-ba9876543210",
      "name": "Production deployment approval",
      "description": "Requires ops team approval",
      "mode": "gated",
      "status": "awaiting_response",
      "timezone": "UTC",
      "execute_at": "2025-01-05T14:00:00Z",
      "idempotency_key": null,
      "coordination_keys": [],
      "snooze_count": 1,
      "created_at": "2025-01-05T09:00:00Z",
      "updated_at": "2025-01-05T14:30:00Z"
    }
  ],
  "links": {
    "first": "https://api.callmelater.io/v1/actions?page=1",
    "last": "https://api.callmelater.io/v1/actions?page=5",
    "prev": null,
    "next": "https://api.callmelater.io/v1/actions?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 25,
    "to": 25,
    "total": 112
  }
}
```

## Response Fields

| Field | Description |
|-------|-------------|
| `data` | Array of action objects |
| `links` | Pagination URLs |
| `meta` | Pagination metadata |

### Action Object (Summary)

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Unique action ID (UUID) |
| `name` | string | Display name |
| `description` | string | Optional description |
| `mode` | string | `immediate` or `gated` |
| `status` | string | Current state |
| `timezone` | string | Timezone used for scheduling |
| `execute_at` | string | Scheduled execution time (ISO 8601) |
| `executed_at` | string | Actual execution time (if completed) |
| `idempotency_key` | string | Your idempotency key (if set) |
| `coordination_keys` | array | Coordination keys for grouping |
| `attempt_count` | integer | Number of delivery attempts (immediate) |
| `max_attempts` | integer | Maximum attempts allowed (immediate) |
| `retry_strategy` | string | `exponential` or `linear` (immediate) |
| `snooze_count` | integer | Number of snoozes used (gated) |
| `failure_reason` | string | Failure description (if failed) |
| `created_at` | string | Creation timestamp |
| `updated_at` | string | Last update timestamp |

## History Limits

The list endpoint respects your plan's history retention:

| Plan | History |
|------|---------|
| Free | 7 days |
| Pro | 90 days |
| Business | 1 year |
| Enterprise | Custom |

Actions older than your plan's retention are not returned, but non-terminal actions (pending, scheduled, etc.) are always visible regardless of age.

## Notes

- Results are ordered by `created_at` descending (newest first)
- Use [Get Action](/api/get-action) for full details including delivery attempts and events
- Terminal actions (executed, failed, cancelled, expired) are hidden after history retention period
