---
sidebar_position: 1
---

# Authentication

All API requests require authentication using a Bearer token.

## Getting an API Token

1. Log in to your [CallMeLater dashboard](https://app.callmelater.io)
2. Navigate to **Settings** → **API Tokens**
3. Click **Create Token**
4. Give it a descriptive name
5. Copy the token — it won't be shown again

Tokens start with `sk_live_` for production.

## Using Your Token

Include the token in the `Authorization` header:

```bash
curl https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_your_token_here" \
  -H "Content-Type: application/json"
```

## Token Scopes

Tokens can have different permission scopes:

| Scope | Permissions |
|-------|-------------|
| `read` | List and view actions |
| `write` | Create, update, and cancel actions |

When creating a token, select the minimal scopes needed.

## Token Management

### List Tokens

```bash
GET /api/tokens
```

Returns all active tokens (values are hidden).

### Create Token

```bash
POST /api/tokens
Content-Type: application/json

{
  "name": "Production Server",
  "abilities": ["read", "write"],
  "expires_at": "2025-12-31"
}
```

### Revoke Token

```bash
DELETE /api/tokens/{token_id}
```

Revoked tokens immediately stop working.

## Security Best Practices

### 1. Use environment variables

```bash
# .env
CALLMELATER_API_KEY=sk_live_your_token_here
```

```javascript
// code
const apiKey = process.env.CALLMELATER_API_KEY;
```

### 2. Rotate tokens regularly

Create new tokens and revoke old ones periodically.

### 3. Use minimal scopes

If you only need to read actions, use a read-only token.

### 4. Set expiration dates

Tokens can have expiration dates. Use them for temporary access.

### 5. Monitor token usage

Check the dashboard for unusual API activity.

## Error Responses

### 401 Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

Causes:
- Missing `Authorization` header
- Invalid token
- Expired token
- Revoked token

### 403 Forbidden

```json
{
  "message": "This action is unauthorized."
}
```

Causes:
- Token lacks required scope
- Accessing another user's resources
