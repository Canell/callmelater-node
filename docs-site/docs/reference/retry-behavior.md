# Retry Behavior

When a webhook request fails, CallMeLater can automatically retry the request based on your configuration.

## When Retries Occur

Retries are triggered when:

- **Server errors (5xx)**: The target server returned an error (500, 502, 503, etc.)
- **Timeouts**: The request timed out before receiving a response
- **Connection failures**: Unable to establish a connection to the target server

Retries are **NOT** triggered for:

- **Client errors (4xx)**: These indicate a problem with the request itself (401, 404, 422, etc.) and retrying won't help
- **Successful responses (2xx)**: The request succeeded

## Retry Strategies

### Exponential Backoff (Recommended)

After each failure, the delay before the next retry increases exponentially:

| Attempt | Delay |
|---------|-------|
| 1st retry | 1 minute |
| 2nd retry | 5 minutes |
| 3rd retry | 15 minutes |
| 4th retry | 1 hour |
| 5th retry | 4 hours |

This strategy is recommended because it:
- Gives the target server time to recover
- Avoids overwhelming a struggling server
- Maximizes the chance of eventual success

### Linear

Retries occur at fixed intervals (e.g., every 5 minutes). This is simpler but may not be ideal if the target server needs time to recover.

## Max Attempts

You can configure the maximum number of attempts (including the initial request). For example:

- **Max Attempts: 1** = No retries, only the initial request
- **Max Attempts: 3** = Initial request + 2 retries
- **Max Attempts: 5** = Initial request + 4 retries (default)

The maximum allowed depends on your plan:

| Plan | Max Attempts |
|------|--------------|
| Free | 3 |
| Pro | 5 |
| Business | 10 |

## Viewing Retry History

Each delivery attempt is logged and visible in the action detail page. You can see:

- Timestamp of each attempt
- HTTP status code received
- Response body (truncated)
- Duration of the request

## Best Practices

1. **Use exponential backoff** for most use cases
2. **Set appropriate max attempts** based on how critical the webhook is
3. **Monitor failed webhooks** in your dashboard
4. **Ensure your endpoint is idempotent** so retries don't cause duplicate actions
