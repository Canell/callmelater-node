---
sidebar_position: 2
---

# Security

CallMeLater is designed with security in mind.

## API Security

### Authentication

All API requests require a Bearer token. Tokens are scoped and can be revoked at any time.

```bash
Authorization: Bearer sk_live_...
```

### HTTPS Only

All API endpoints are served over HTTPS. HTTP requests are rejected.

### Token Best Practices

- Store tokens in environment variables, not code
- Use minimal scopes (read-only when possible)
- Set expiration dates for temporary access
- Rotate tokens periodically
- Revoke unused tokens

## Webhook Security

### HMAC Signatures

When you provide a `webhook_secret`, outgoing requests are signed:

```
X-CallMeLater-Signature: sha256=<hmac>
X-CallMeLater-Action-Id: <uuid>
X-CallMeLater-Timestamp: <unix>
```

Always verify signatures in production. See [Webhooks](../api/webhooks) for implementation examples.

### Timestamp Validation

Reject requests with timestamps too far in the past to prevent replay attacks:

```javascript
const timestamp = req.headers['x-callmelater-timestamp'];
const age = Date.now() / 1000 - parseInt(timestamp);

if (age > 300) { // 5 minutes
  return res.status(401).send('Request too old');
}
```

## SSRF Protection

CallMeLater prevents Server-Side Request Forgery attacks.

### Blocked Destinations

Requests are blocked to:

- Private IP ranges (`10.x.x.x`, `192.168.x.x`, `172.16-31.x.x`)
- Loopback addresses (`127.x.x.x`, `::1`)
- Link-local addresses (`169.254.x.x`)
- Cloud metadata endpoints (`169.254.169.254`)
- Internal hostnames (`localhost`, `*.local`, `*.internal`)

### DNS Rebinding Protection

Hostnames are resolved before the request, and IPs are validated. This prevents DNS rebinding attacks where a hostname resolves to a private IP.

## Data Security

### Encryption at Rest

All data is encrypted at rest using AES-256.

### Encryption in Transit

All connections use TLS 1.2 or higher.

### Data Retention

| Plan | Retention |
|------|-----------|
| Free | 7 days |
| Pro | 90 days |
| Business | 1 year |
| Enterprise | Custom |

After the retention period, action data is permanently deleted.

### Sensitive Data

Avoid including sensitive data (passwords, API keys, PII) in action payloads. If necessary:

- Use references/IDs instead of actual data
- Encrypt sensitive fields yourself
- Consider using your own signed tokens

## Rate Limiting

Rate limits protect against abuse and ensure service availability. See [Rate Limits](./rate-limits) for details.

## Reporting Security Issues

If you discover a security vulnerability, please report it responsibly:

- Email: security@callmelater.io
- Do not disclose publicly until we've addressed the issue
- We aim to respond within 24 hours
