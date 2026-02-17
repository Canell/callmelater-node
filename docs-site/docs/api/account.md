---
sidebar_position: 5
---

# Account & Settings

Endpoints for managing your account quota, contacts, and domain verification.

## Quota

```
GET /quota
```

Returns your current billing period usage and plan limits.

### Response

```json
{
  "period": {
    "start": "2026-02-01T00:00:00Z",
    "end": "2026-02-28T23:59:59Z"
  },
  "actions": {
    "used": 47,
    "limit": 5000
  },
  "sms": {
    "used": 12,
    "limit": 100
  },
  "plan": {
    "name": "Pro",
    "features": [
      "webhook_signatures",
      "sms_reminders",
      "team_features"
    ]
  }
}
```

Usage resets on the 1st of each month at 00:00 UTC. Actions in `cancelled` status still count toward your monthly usage. Specific limits depend on your plan.

---

## Contacts

Manage reusable recipient entries that can be referenced by ID in approval recipients instead of raw email addresses or phone numbers.

### List Contacts

```
GET /contacts
```

```json
{
  "data": [
    {
      "id": "contact-uuid-1",
      "name": "John Smith",
      "email": "john@example.com",
      "phone": "+15551234567",
      "created_at": "2026-01-01T10:00:00Z",
      "updated_at": "2026-01-01T10:00:00Z"
    }
  ]
}
```

### Create Contact

```
POST /contacts
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Display name (max 255 chars) |
| `email` | string | No* | Email address |
| `phone` | string | No* | Phone number in E.164 format (e.g., `+15551234567`) |

*At least one of `email` or `phone` is required.

### Get Contact

```
GET /contacts/{id}
```

### Update Contact

```
PUT /contacts/{id}
```

All fields are optional. Only provided fields are updated.

### Delete Contact

```
DELETE /contacts/{id}
```

### Usage in Approvals

Reference contacts by ID in the `gate.recipients` array using the format `contact:{id}:email` or `contact:{id}:phone`:

```json
{
  "mode": "approval",
  "gate": {
    "message": "Please approve deployment",
    "recipients": ["contact:contact-uuid-1:email", "contact:contact-uuid-2:phone"],
    "channels": ["email", "sms"]
  }
}
```

The system looks up each contact's information and sends through the specified channel.

---

## Domain Verification

Domain verification is required when your account exceeds usage thresholds to a single domain:

- More than **10 actions per day** to the same domain
- More than **100 actions per month** to the same domain

Until verified, new actions targeting that domain will be rejected with a `422` error.

### List Domains

```
GET /domains
```

Returns all domains associated with your account and their verification status.

```json
{
  "data": [
    {
      "domain": "api.example.com",
      "verified": true,
      "verified_at": "2026-01-01T10:00:00Z",
      "method": "dns"
    },
    {
      "domain": "hooks.myapp.io",
      "verified": false,
      "verified_at": null,
      "method": null
    }
  ]
}
```

### Start Verification

```
POST /domains/{domain}/verify
```

Initiates the verification process and returns instructions for both supported methods.

```json
{
  "domain": "api.example.com",
  "verification_token": "cml_abc123def456",
  "verification_methods": {
    "dns": {
      "type": "TXT",
      "value": "callmelater-verification=cml_abc123def456"
    },
    "file": {
      "url": "https://api.example.com/.well-known/callmelater-verify.txt",
      "content": "callmelater-verification=cml_abc123def456"
    }
  }
}
```

**Method 1 -- DNS TXT Record:** Add a TXT record with the provided value to your domain's DNS. Allow up to 24 hours for propagation, then check verification status.

**Method 2 -- File Verification:** Create a publicly accessible file at `https://yourdomain.com/.well-known/callmelater-verify.txt` containing the verification string.

### Get Domain

```
GET /domains/{domain}
```

Returns verification instructions and current status for a specific domain.

### Remove Domain

```
DELETE /domains/{domain}
```

Removes the domain from your account. You will need to re-verify if you exceed thresholds again.

### Notes

- Subdomains are verified independently (`api.example.com` and `www.example.com` are separate)
- Once verified, domain verification does not expire
- Wildcard verification is not supported
