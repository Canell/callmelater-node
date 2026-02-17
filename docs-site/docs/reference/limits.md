---
sidebar_position: 2
---

# Limits, Plans & Changelog

## API Rate Limits

| Endpoint | Authenticated | Unauthenticated |
|----------|---------------|-----------------|
| All API endpoints | 100 req/min | 20 req/min |
| Create action | 100 req/hour | N/A |
| Reminder response | N/A | 10 req/min per token |
| Template trigger | 60 req/min per token | 120 req/min per IP |

### Rate limit headers

Every response includes:

| Header | Description |
|--------|-------------|
| `X-RateLimit-Limit` | Max requests in the window |
| `X-RateLimit-Remaining` | Requests remaining |
| `X-RateLimit-Reset` | Unix timestamp when limit resets |

When exceeded, you'll receive `429 Too Many Requests` with a `Retry-After` header.

## Plan Limits

Plan limits (actions per month, retry attempts, templates, retention) vary by plan. See your dashboard or [pricing page](https://callmelater.io/pricing) for current limits.

## Backwards Compatibility

The API accepts both old and new field names. **New names are recommended.** Old names will be removed in API v2.

| Current Name | Old Name (deprecated) |
|---|---|
| `schedule` | `intent` |
| `schedule.wait` | `intent.delay` |
| `scheduled_for` | `execute_at` / `execute_at_utc` |
| `mode: "webhook"` | `mode: "immediate"` / `type: "immediate"` |
| `mode: "approval"` | `mode: "gated"` / `type: "gated"` |
| Status: `scheduled` | `pending_resolution` |
| `dedup_keys` | `coordination_keys` |
| `coordination` | _(no alias — only `coordination` is accepted)_ |
| `GET /coordination-keys` | _(no alias — only `/coordination-keys` exists)_ |

## Changelog

### 2025-01-04

**Initial public release**

- HTTP action scheduling with exponential and linear retry strategies
- Approval actions with email and SMS delivery
- Idempotency key support and cancel-by-key
- Webhook signatures (HMAC-SHA256)
- SSRF protection for outgoing requests
- Chains (multi-step workflows)
- Templates with placeholder support
- Domain verification
- Team member management

### Versioning Policy

- Breaking changes increment the major version (v1 → v2)
- New features are added without version change
- Deprecated features are maintained for at least 6 months

| Version | Status |
|---------|--------|
| v1 | Current |
