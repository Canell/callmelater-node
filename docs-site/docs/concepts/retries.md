---
sidebar_position: 3
---

# Retry Strategies

When HTTP requests fail, CallMeLater automatically retries with configurable strategies.

## How Retries Work

1. Action is dispatched at scheduled time
2. Request fails (network error, timeout, non-2xx response)
3. CallMeLater schedules a retry based on your strategy
4. Process repeats until success or max attempts reached

## Retry Strategies

### Exponential Backoff (Default)

Delays increase exponentially:

| Attempt | Delay |
|---------|-------|
| 1 | Immediate |
| 2 | 1 minute |
| 3 | 5 minutes |
| 4 | 15 minutes |
| 5 | 1 hour |
| 6 | 4 hours |

```json
{
  "type": "http",
  "retry_strategy": "exponential",
  "max_attempts": 5,
  ...
}
```

Best for: Most use cases. Gives transient issues time to resolve.

### Linear Backoff

Fixed delay between attempts:

| Attempt | Delay |
|---------|-------|
| 1 | Immediate |
| 2 | 5 minutes |
| 3 | 5 minutes |
| 4 | 5 minutes |

```json
{
  "type": "http",
  "retry_strategy": "linear",
  "max_attempts": 5,
  ...
}
```

Best for: Time-sensitive actions where you want consistent retry intervals.

## Configuration

### Max Attempts

Set the maximum number of delivery attempts:

```json
{
  "max_attempts": 10
}
```

Default: 5 attempts

Plan limits:
- Free: 3 attempts max
- Pro: 10 attempts max
- Business+: Unlimited

### What Triggers a Retry

Retries occur when:
- Connection timeout
- Network error
- HTTP 5xx response (server error)
- HTTP 429 response (rate limited)

No retry for:
- HTTP 2xx (success)
- HTTP 4xx except 429 (client error — fix your request)

## Viewing Retry History

Each delivery attempt is logged:

```json
{
  "delivery_attempts": [
    {
      "attempt_number": 1,
      "status": "failed",
      "response_code": 503,
      "error_message": "Service Unavailable",
      "duration_ms": 1523,
      "attempted_at": "2025-01-05T09:00:00Z"
    },
    {
      "attempt_number": 2,
      "status": "success",
      "response_code": 200,
      "duration_ms": 234,
      "attempted_at": "2025-01-05T09:01:00Z"
    }
  ]
}
```

## Best Practices

1. **Set appropriate max_attempts** — More attempts for critical actions
2. **Make webhooks idempotent** — Your endpoint may receive the same payload twice
3. **Return 2xx quickly** — Process async if needed, confirm receipt immediately
4. **Use webhook signatures** — Verify requests are from CallMeLater
