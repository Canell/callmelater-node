---
sidebar_position: 1
---

# Rate Limits

CallMeLater applies rate limits to ensure fair usage and system stability.

## API Rate Limits

| Endpoint | Authenticated | Unauthenticated |
|----------|---------------|-----------------|
| All API endpoints | 100 req/min | 20 req/min |
| Create action | 100 req/hour | N/A |
| Reminder response | N/A | 10 req/min per token |

## Plan Limits

| Feature | Free | Pro | Business | Enterprise |
|---------|------|-----|----------|------------|
| Actions per month | 100 | 5,000 | 25,000 | Unlimited |
| Max retry attempts | 3 | 10 | Unlimited | Unlimited |
| SMS reminders | No | Yes | Yes | Yes |
| History retention | 7 days | 90 days | 1 year | Custom |

## Rate Limit Headers

Every response includes rate limit information:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1704470400
```

| Header | Description |
|--------|-------------|
| `X-RateLimit-Limit` | Max requests in the window |
| `X-RateLimit-Remaining` | Requests remaining |
| `X-RateLimit-Reset` | Unix timestamp when limit resets |

## Exceeded Rate Limit

When you exceed the rate limit, you'll receive:

```
HTTP/1.1 429 Too Many Requests
Retry-After: 42
```

```json
{
  "message": "Too Many Attempts.",
  "retry_after": 42
}
```

## Best Practices

### 1. Check remaining limits

Monitor the `X-RateLimit-Remaining` header and slow down before hitting the limit.

### 2. Implement exponential backoff

When rate limited, wait before retrying:

```javascript
async function makeRequest(retries = 3) {
  try {
    return await callApi();
  } catch (error) {
    if (error.status === 429 && retries > 0) {
      const delay = error.retryAfter || Math.pow(2, 3 - retries) * 1000;
      await sleep(delay);
      return makeRequest(retries - 1);
    }
    throw error;
  }
}
```

### 3. Batch operations

Instead of creating actions one by one, consider creating them in batches during off-peak times.

### 4. Cache responses

Cache action details locally instead of fetching them repeatedly.

## Requesting Higher Limits

If you need higher limits:

1. **Pro/Business plans** have higher limits included
2. **Enterprise plans** can be customized for your needs
3. Contact support for temporary limit increases during migrations
