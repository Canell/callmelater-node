---
sidebar_position: 1
---

# Authentication

All API requests require authentication using a Bearer token.

## Getting an API Token

1. Log in to your [CallMeLater dashboard](https://app.callmelater.io)
2. Navigate to **Settings** -> **API Tokens**
3. Click **Create Token**
4. Give it a descriptive name and select the required scopes
5. Copy the token immediately -- it will not be shown again

All tokens use the `sk_live_` prefix.

## Using Your Token

Include the token in the `Authorization` header of every request:

```bash
curl https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_your_token_here" \
  -H "Content-Type: application/json"
```

## Token Scopes

| Scope | Permissions |
|-------|-------------|
| `read` | List and retrieve actions, chains, templates, team members, quota |
| `write` | Create, cancel, and retry actions; manage chains, templates, and team members |

When creating a token, select only the scopes your integration requires.

## Error Responses

### 401 Unauthorized

Returned when the token is missing, invalid, expired, or revoked.

```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden

Returned when the token lacks the required scope for the requested operation.

```json
{
  "message": "This action is unauthorized."
}
```

## Best Practices

- **Store tokens in environment variables.** Never hard-code tokens in source code or commit them to version control. Use `CALLMELATER_API_KEY` in your `.env` file and reference it at runtime.

- **Rotate tokens regularly.** Create a new token, update your integrations, then revoke the old one. Set expiration dates for tokens used in temporary or CI/CD contexts.

- **Use the minimum required scope.** If your integration only reads action statuses, issue a `read`-only token. Reserve `write` scope for services that create or cancel actions.
