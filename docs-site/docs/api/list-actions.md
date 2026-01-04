---
sidebar_position: 3
---

# List Actions

Retrieve a paginated list of your actions.

```
GET /api/v1/actions
```

## Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | — | Filter by status |
| `type` | string | — | Filter by type (`http` or `reminder`) |
| `per_page` | integer | 25 | Results per page (max 100) |
| `page` | integer | 1 | Page number |

### Status Values

- `pending_resolution`
- `resolved`
- `awaiting_response`
- `executed`
- `failed`
- `cancelled`

## Examples

### List All Actions

```bash
curl https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..."
```

### Filter by Status

```bash
curl "https://api.callmelater.io/v1/actions?status=resolved" \
  -H "Authorization: Bearer sk_live_..."
```

### Filter by Type

```bash
curl "https://api.callmelater.io/v1/actions?type=reminder" \
  -H "Authorization: Bearer sk_live_..."
```

### Pagination

```bash
curl "https://api.callmelater.io/v1/actions?per_page=50&page=2" \
  -H "Authorization: Bearer sk_live_..."
```

## Response

```json
{
  "data": [
    {
      "id": "01234567-89ab-cdef-0123-456789abcdef",
      "type": "http",
      "idempotency_key": "trial-end-user-42",
      "resolution_status": "resolved",
      "execute_at_utc": "2025-01-08T09:00:00Z",
      "attempt_count": 0,
      "max_attempts": 5,
      "created_at": "2025-01-05T10:30:00Z"
    },
    {
      "id": "fedcba98-7654-3210-fedc-ba9876543210",
      "type": "reminder",
      "idempotency_key": null,
      "resolution_status": "awaiting_response",
      "execute_at_utc": "2025-01-05T14:00:00Z",
      "created_at": "2025-01-05T09:00:00Z"
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

### Action Object

| Field | Description |
|-------|-------------|
| `id` | Unique action ID (UUID) |
| `type` | `http` or `reminder` |
| `idempotency_key` | Your idempotency key (if set) |
| `resolution_status` | Current state |
| `execute_at_utc` | Scheduled execution time |
| `attempt_count` | Number of delivery attempts |
| `max_attempts` | Maximum attempts allowed |
| `created_at` | When the action was created |
