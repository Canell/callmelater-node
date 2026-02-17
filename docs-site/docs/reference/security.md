---
sidebar_position: 1
---

# Security & Webhooks

## Receiving Webhooks

When CallMeLater delivers an HTTP action, it sends a signed request to your endpoint.

### Request headers

| Header | Description |
|--------|-------------|
| `Content-Type` | `application/json` |
| `User-Agent` | `CallMeLater/1.0` |
| `X-CallMeLater-Action-Id` | Action UUID |
| `X-CallMeLater-Timestamp` | Unix timestamp |
| `X-CallMeLater-Signature` | HMAC signature (if secret configured) |

The body is the exact JSON you provided when creating the action, plus any custom headers you specified.

### Response expectations

| Your Response | What Happens |
|---------------|-------------|
| `2xx` | Success — action marked as `executed` |
| `4xx` (except 429) | Permanent failure — no retry |
| `429` | Rate limited — retry with backoff |
| `5xx` | Temporary failure — retry |
| Timeout (30s) | Retry |

:::tip
Return 200 immediately and process asynchronously. Requests timeout after 30 seconds.
:::

## Webhook Signatures

Actions are signed with your webhook secret (Settings → Webhook Secret) or the per-action `webhook_secret`.

```
X-CallMeLater-Signature: sha256=5d41402abc4b2a76b9719d911017c592
```

### Verifying signatures

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
```

**Python**
```python
import hmac, hashlib

def verify_signature(payload: bytes, signature: str, secret: str) -> bool:
    expected = 'sha256=' + hmac.new(
        secret.encode(), payload, hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, signature)
```

:::info
The Node.js and Laravel SDKs handle signature verification automatically. See [Node.js SDK](/sdks/nodejs#webhooks) or [Laravel SDK](/sdks/laravel#webhooks).
:::

### Timestamp validation

Reject old requests to prevent replay attacks:

```javascript
const age = Date.now() / 1000 - parseInt(req.headers['x-callmelater-timestamp']);
if (age > 300) return res.status(401).send('Request too old'); // 5 minutes
```

## SSRF Protection

Requests are blocked to:

- Private IPs (`10.x.x.x`, `192.168.x.x`, `172.16-31.x.x`)
- Loopback (`127.x.x.x`, `::1`, `localhost`)
- Link-local (`169.254.x.x`)
- Cloud metadata (`169.254.169.254`)
- Internal hostnames (`*.local`, `*.internal`)

DNS rebinding is also prevented — hostnames are resolved and IPs validated before requests.

## IP Allowlisting

All outbound HTTP calls originate from:

```
203.0.113.50
```

You can also fetch this from `https://callmelater.io/api/public/server-info`.

### Firewall examples

**AWS Security Group:**
```
Type: Custom TCP | Port: 443 | Source: 203.0.113.50/32
```

**nginx:**
```nginx
location /webhook {
    allow 203.0.113.50;
    deny all;
}
```

## Data Security

- **Encryption at rest:** AES-256
- **Encryption in transit:** TLS 1.2+
- **HTTPS only:** HTTP requests are rejected

Avoid including passwords, API keys, or PII in action payloads. Use references/IDs instead.

## Reporting Issues

Email security@callmelater.io. We respond within 24 hours.
