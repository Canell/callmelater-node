---
sidebar_position: 3
---

# Changelog

All notable changes to the CallMeLater API.

## 2025-01-04

### Added
- Initial public release
- HTTP action scheduling with retry strategies
- Reminder actions with email and SMS delivery
- Idempotency key support
- Cancel by idempotency key endpoint
- Webhook signatures (HMAC-SHA256)
- SSRF protection for outgoing requests

### API Endpoints
- `POST /api/v1/actions` - Create action
- `GET /api/v1/actions` - List actions
- `GET /api/v1/actions/{id}` - Get action details
- `DELETE /api/v1/actions/{id}` - Cancel by ID
- `DELETE /api/v1/actions` - Cancel by idempotency key
- `POST /api/v1/respond` - Handle reminder responses
- `GET /api/tokens` - List API tokens
- `POST /api/tokens` - Create API token
- `DELETE /api/tokens/{id}` - Revoke API token

---

## Versioning Policy

The CallMeLater API follows semantic versioning:

- **Breaking changes** increment the major version (v1 → v2)
- **New features** are added without version change
- **Bug fixes** are applied to all supported versions

### Deprecation

When we deprecate a feature:

1. We announce it in the changelog
2. We add deprecation warnings to the API response
3. We maintain the old behavior for at least 6 months
4. We provide migration guides

### Version Support

| Version | Status | End of Life |
|---------|--------|-------------|
| v1 | Current | — |

---

## Subscribe to Updates

Stay informed about API changes:

- Follow our [status page](https://status.callmelater.io)
- Subscribe to the changelog RSS feed
- Join our developer newsletter
