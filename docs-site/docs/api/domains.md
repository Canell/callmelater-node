---
sidebar_position: 12
---

# Domain Verification

Verify ownership of domains used in webhook URLs. Verification is required when exceeding usage thresholds to a specific domain.

## When Is Verification Required?

Domain verification is triggered when:
- **Daily threshold**: More than 10 actions/day to the same domain
- **Monthly threshold**: More than 100 actions/month to the same domain

Until verified, new actions to that domain will be rejected.

## List Domains

```
GET /api/v1/domains
```

### Response

```json
{
  "data": [
    {
      "id": "domain-uuid-1",
      "domain": "api.example.com",
      "verified": true,
      "verified_at": "2025-01-01T10:00:00Z",
      "method": "dns",
      "verification_token": "cml_abc123..."
    },
    {
      "id": "domain-uuid-2",
      "domain": "hooks.myapp.io",
      "verified": false,
      "verified_at": null,
      "method": null,
      "verification_token": "cml_def456..."
    }
  ]
}
```

## Get Domain / Start Verification

Get verification instructions for a specific domain. Creates a verification record if one doesn't exist.

```
GET /api/v1/domains/{domain}
```

### Example

```bash
curl https://api.callmelater.io/v1/domains/api.example.com \
  -H "Authorization: Bearer sk_live_..."
```

### Response

```json
{
  "domain": "api.example.com",
  "verified": false,
  "verification_token": "cml_abc123def456",
  "verification_methods": {
    "dns": {
      "type": "TXT",
      "value": "callmelater-verification=cml_abc123def456",
      "instructions": "Add a TXT record to your domain's DNS with the value above"
    },
    "file": {
      "url": "https://api.example.com/.well-known/callmelater.txt",
      "content": "callmelater-verification=cml_abc123def456",
      "instructions": "Create a file at the URL above containing the verification string"
    }
  }
}
```

## Verify Domain

Trigger verification check using configured method (DNS or file).

```
POST /api/v1/domains/{domain}/verify
```

### Example

```bash
curl -X POST https://api.callmelater.io/v1/domains/api.example.com/verify \
  -H "Authorization: Bearer sk_live_..."
```

### Success Response (200 OK)

```json
{
  "verified": true,
  "message": "Domain verified successfully.",
  "method": "dns"
}
```

### Verification Failed (422)

```json
{
  "message": "Domain verification failed",
  "domain": "api.example.com",
  "checked_methods": ["dns", "file"],
  "instructions": {
    "dns": {
      "type": "TXT",
      "expected": "callmelater-verification=cml_abc123def456"
    },
    "file": {
      "url": "https://api.example.com/.well-known/callmelater.txt",
      "expected": "callmelater-verification=cml_abc123def456"
    }
  }
}
```

## Delete Domain

Remove a verified domain from your account.

```
DELETE /api/v1/domains/{domain}
```

### Response (200 OK)

```json
{
  "message": "Domain removed"
}
```

## Verification Methods

### DNS TXT Record (Recommended)

1. Add a TXT record to your domain's DNS:
   - **Type**: TXT
   - **Name/Host**: `@` or your domain
   - **Value**: `callmelater-verification=cml_yourtoken`

2. Wait for DNS propagation (typically 1-24 hours)

3. Call the verify endpoint

### File Verification

1. Create a file at:
   ```
   https://yourdomain.com/.well-known/callmelater.txt
   ```

2. File contents (exactly):
   ```
   callmelater-verification=cml_yourtoken
   ```

3. Ensure the file is publicly accessible via HTTPS

4. Call the verify endpoint

## Error Responses

### Domain Verification Required (422)

When creating an action without verified domain:

```json
{
  "message": "Domain verification required",
  "domain": "api.example.com",
  "verification_url": "https://app.callmelater.io/settings/domains/api.example.com",
  "reason": "daily_threshold_exceeded"
}
```

## Notes

- **Domain verification is permanent** — once verified, domains never expire
- Admin users bypass domain verification requirements
- Subdomains are verified separately (api.example.com ≠ www.example.com)
- Wildcard verification is not supported
- localhost and private IPs are blocked for security
