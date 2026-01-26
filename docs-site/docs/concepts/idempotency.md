---
sidebar_position: 5
---

# Idempotency

Idempotency keys prevent duplicate actions when your system retries requests.

## The Problem

Your code schedules a webhook:

```javascript
await callmelater.createAction({
  intent: { preset: 'tomorrow' },
  request: { url: 'https://...' }
});
```

But what if:
- Your request times out (but CallMeLater received it)
- Your code crashes and retries
- A user double-clicks a button

Without idempotency, you might schedule the same action twice.

## The Solution

Add an `idempotency_key`:

```javascript
await callmelater.createAction({
  idempotency_key: 'trial-end-user-42',
  intent: { preset: 'tomorrow' },
  request: { url: 'https://...' }
});
```

Now if you send the same request twice:
- First request → creates the action
- Second request → returns the existing action (no duplicate)

## Key Format

Idempotency keys are:
- Unique per user account
- Up to 255 characters
- Case-sensitive

Good patterns:

```
# Entity + ID + purpose
"trial-end-user-42"
"invoice-1234-reminder"
"deploy-abc123-approval"

# Date-based for recurring
"weekly-report-2025-W01"
"daily-cleanup-2025-01-05"
```

## Behavior by State

| Existing Action State | Behavior |
|-----------------------|----------|
| `pending_resolution` | Returns existing action |
| `resolved` | Returns existing action |
| `awaiting_response` | Returns existing action |
| `executed` | Returns error (409 Conflict) |
| `failed` | Returns error (409 Conflict) |
| `cancelled` | Returns error (409 Conflict) |

Once an action reaches a terminal state, the idempotency key cannot be reused.

## Cancel by Idempotency Key

You can cancel actions using the idempotency key instead of the ID:

```bash
DELETE /api/v1/actions
Content-Type: application/json

{
  "idempotency_key": "trial-end-user-42"
}
```

This is useful when you don't have the action ID stored.

## Best Practices

### 1. Always use idempotency keys for critical actions

```javascript
// Good
await createAction({
  idempotency_key: `payment-reminder-${invoiceId}`,
  ...
});

// Risky
await createAction({
  // No idempotency key - duplicates possible
  ...
});
```

### 2. Include relevant context in the key

```javascript
// Good - specific to this exact use case
idempotency_key: `trial-end-user-${userId}-${trialId}`

// Less good - might collide
idempotency_key: `user-${userId}`
```

### 3. Use deterministic keys

```javascript
// Good - same input = same key
const key = `order-${orderId}-confirmation`;

// Bad - different every time
const key = `order-${Date.now()}`;
```

## Checking Existing Actions

To check if an action exists without creating one, use GET:

```bash
GET /api/v1/actions?idempotency_key=trial-end-user-42
```

This returns the action if it exists, without creating a new one.
