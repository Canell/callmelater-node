---
sidebar_position: 10
---

# Quota & Usage

Get current usage statistics and plan limits for your account.

```
GET /api/v1/quota
```

## Example

```bash
curl https://api.callmelater.io/v1/quota \
  -H "Authorization: Bearer sk_live_..."
```

## Response

```json
{
  "period": {
    "year": 2025,
    "month": 1,
    "month_name": "January 2025"
  },
  "actions": {
    "used": 47,
    "limit": 100,
    "remaining": 53
  },
  "sms": {
    "used": 12,
    "limit": 50,
    "remaining": 38
  },
  "plan": {
    "name": "Pro",
    "actions_limit": 5000,
    "sms_limit": 500,
    "max_attempts": 10,
    "history_days": 90,
    "features": [
      "webhook_signatures",
      "sms_reminders",
      "team_features"
    ]
  }
}
```

## Response Fields

### Period

| Field | Type | Description |
|-------|------|-------------|
| `year` | integer | Current billing year |
| `month` | integer | Current billing month (1-12) |
| `month_name` | string | Human-readable period name |

### Actions

| Field | Type | Description |
|-------|------|-------------|
| `used` | integer | Actions created this period |
| `limit` | integer | Maximum actions allowed |
| `remaining` | integer | Actions remaining |

### SMS

| Field | Type | Description |
|-------|------|-------------|
| `used` | integer | SMS messages sent this period |
| `limit` | integer | Maximum SMS allowed |
| `remaining` | integer | SMS remaining |

### Plan

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Plan name (Free, Pro, Business, Enterprise) |
| `actions_limit` | integer | Monthly action limit |
| `sms_limit` | integer | Monthly SMS limit |
| `max_attempts` | integer | Maximum retry attempts per action |
| `history_days` | integer | Days of action history retained |
| `features` | array | Enabled features for this plan |

## Plan Limits

| Plan | Actions/Month | SMS/Month | Max Attempts | History |
|------|--------------|-----------|--------------|---------|
| Free | 100 | 0 | 3 | 7 days |
| Pro | 5,000 | 500 | 10 | 90 days |
| Business | 25,000 | 2,500 | Unlimited | 1 year |
| Enterprise | Unlimited | Unlimited | Unlimited | Custom |

## Notes

- Usage resets on the 1st of each month at 00:00 UTC
- Actions in `cancelled` status still count toward usage
- SMS usage only applies when using SMS channel in gated reminders
- Exceeding limits returns a 422 error when creating actions
