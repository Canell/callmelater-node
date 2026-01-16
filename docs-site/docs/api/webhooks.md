---
sidebar_position: 6
---

# Webhooks

When CallMeLater delivers HTTP actions, it sends signed requests to your endpoint.

## Request Format

### Headers

| Header | Description |
|--------|-------------|
| `Content-Type` | `application/json` |
| `User-Agent` | `CallMeLater/1.0` |
| `X-CallMeLater-Action-Id` | Action UUID |
| `X-CallMeLater-Timestamp` | Unix timestamp |
| `X-CallMeLater-Signature` | HMAC signature (if secret configured) |

Plus any custom headers you specified in the action.

### Body

The exact JSON body you provided when creating the action.

## Webhook Signatures

Every HTTP action is signed using your account's webhook secret (found in Settings → Webhook Secret). You can also override it per-action by setting `webhook_secret` when creating the action.

### Signature Header

```
X-CallMeLater-Signature: sha256=5d41402abc4b2a76b9719d911017c592
```

### Verifying Signatures

**PHP**
```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_CALLMELATER_SIGNATURE'];
$expected = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}
```

**Node.js**
```javascript
const crypto = require('crypto');

function verifySignature(payload, signature, secret) {
  const expected = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');

  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expected)
  );
}

// In your handler
const payload = req.rawBody; // Raw request body
const signature = req.headers['x-callmelater-signature'];

if (!verifySignature(payload, signature, process.env.WEBHOOK_SECRET)) {
  return res.status(401).send('Invalid signature');
}
```

**Python**
```python
import hmac
import hashlib

def verify_signature(payload: bytes, signature: str, secret: str) -> bool:
    expected = 'sha256=' + hmac.new(
        secret.encode(),
        payload,
        hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, signature)

# In your handler
payload = request.get_data()
signature = request.headers.get('X-CallMeLater-Signature')

if not verify_signature(payload, signature, WEBHOOK_SECRET):
    abort(401)
```

## Response Expectations

### Success

Return any `2xx` status code to confirm receipt:

```
HTTP/1.1 200 OK
```

The action will be marked as `executed`.

### Failure

Return a `5xx` status code to trigger a retry:

```
HTTP/1.1 503 Service Unavailable
```

Return a `4xx` status code (except 429) for permanent failures:

```
HTTP/1.1 400 Bad Request
```

No retry will be attempted.

### Rate Limiting

Return `429 Too Many Requests` to trigger a retry with backoff:

```
HTTP/1.1 429 Too Many Requests
Retry-After: 60
```

## Best Practices

### 1. Respond quickly

Return a 200 immediately, then process asynchronously:

```javascript
app.post('/webhook', (req, res) => {
  // Acknowledge immediately
  res.status(200).send('OK');

  // Process in background
  processWebhook(req.body);
});
```

### 2. Make handlers idempotent

The same webhook might be delivered twice. Use the action ID to deduplicate:

```javascript
const actionId = req.headers['x-callmelater-action-id'];

if (await alreadyProcessed(actionId)) {
  return res.status(200).send('Already processed');
}

await processWebhook(req.body);
await markAsProcessed(actionId);
```

### 3. Log the action ID

Include it in your logs for debugging:

```javascript
console.log(`Processing webhook for action: ${actionId}`);
```

### 4. Verify signatures

Always verify signatures in production to ensure requests are from CallMeLater.

## Timeout

Requests timeout after **30 seconds**. If your processing takes longer, return 200 immediately and process asynchronously.
