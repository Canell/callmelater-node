---
sidebar_position: 5
---

# Cancel Action

Cancel a pending action before it executes.

## Cancel by ID

```
DELETE /api/v1/actions/{id}
```

### Example

```bash
curl -X DELETE https://api.callmelater.io/v1/actions/01234567-89ab-cdef-0123-456789abcdef \
  -H "Authorization: Bearer sk_live_..."
```

### Response

**Success (200)**
```json
{
  "message": "Action cancelled"
}
```

**Already executed (422)**
```json
{
  "message": "Cannot cancel an action that has already been executed"
}
```

**Not found (404)**
```json
{
  "message": "Action not found"
}
```

## Cancel by Idempotency Key

Cancel an action using its idempotency key instead of the ID.

```
DELETE /api/v1/actions
```

### Request Body

```json
{
  "idempotency_key": "trial-end-user-42"
}
```

### Example

```bash
curl -X DELETE https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "idempotency_key": "trial-end-user-42"
  }'
```

### Response

**Cancelled (200)**
```json
{
  "message": "Action cancelled",
  "id": "01234567-89ab-cdef-0123-456789abcdef"
}
```

**Already cancelled (200)** — Idempotent behavior
```json
{
  "message": "Action already cancelled",
  "id": "01234567-89ab-cdef-0123-456789abcdef"
}
```

**Already executed (409)**
```json
{
  "message": "Action already executed",
  "id": "01234567-89ab-cdef-0123-456789abcdef"
}
```

**Not found (404)**
```json
{
  "message": "Action not found"
}
```

## Cancellation Rules

| Current Status | Can Cancel? |
|----------------|-------------|
| `pending_resolution` | Yes |
| `resolved` | Yes |
| `awaiting_response` | Yes |
| `executed` | No |
| `failed` | No |
| `cancelled` | Already cancelled (returns 200) |

## Use Cases

### Cancel a scheduled webhook

```javascript
// User cancelled their trial before it expires
await callmelater.cancelAction({
  idempotency_key: `trial-end-user-${userId}`
});
```

### Cancel a pending reminder

```javascript
// Approval was handled through another channel
await callmelater.cancelAction({
  idempotency_key: `deploy-approval-${deploymentId}`
});
```

## Best Practices

1. **Use idempotency keys for cancellation** — You often don't have the action ID readily available

2. **Check the response status** — 200 means cancelled (or already cancelled), 409 means too late

3. **Handle race conditions** — The action might execute between your check and cancel request
