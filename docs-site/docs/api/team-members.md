---
sidebar_position: 11
---

# Team Members

Manage contacts for gated reminders. Team members can be referenced by ID in reminder recipients instead of raw email/phone.

## List Team Members

```
GET /api/v1/team-members
```

### Example

```bash
curl https://api.callmelater.io/v1/team-members \
  -H "Authorization: Bearer sk_live_..."
```

### Response

```json
{
  "data": [
    {
      "id": "member-uuid-1",
      "name": "John Smith",
      "email": "john@example.com",
      "phone": "+15551234567",
      "created_at": "2025-01-01T10:00:00Z",
      "updated_at": "2025-01-01T10:00:00Z"
    },
    {
      "id": "member-uuid-2",
      "name": "Jane Ops",
      "email": "jane@example.com",
      "phone": null,
      "created_at": "2025-01-02T10:00:00Z",
      "updated_at": "2025-01-02T10:00:00Z"
    }
  ]
}
```

## Create Team Member

```
POST /api/v1/team-members
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Display name (max 255 chars) |
| `email` | string | No* | Email address |
| `phone` | string | No* | Phone number in E.164 format |

*At least one of `email` or `phone` is required.

### Example

```bash
curl -X POST https://api.callmelater.io/v1/team-members \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Team Member",
    "email": "new@example.com",
    "phone": "+15559876543"
  }'
```

### Response (201 Created)

```json
{
  "data": {
    "id": "member-uuid-new",
    "name": "New Team Member",
    "email": "new@example.com",
    "phone": "+15559876543",
    "created_at": "2025-01-08T10:00:00Z",
    "updated_at": "2025-01-08T10:00:00Z"
  }
}
```

## Get Team Member

```
GET /api/v1/team-members/{id}
```

### Response

```json
{
  "data": {
    "id": "member-uuid-1",
    "name": "John Smith",
    "email": "john@example.com",
    "phone": "+15551234567",
    "created_at": "2025-01-01T10:00:00Z",
    "updated_at": "2025-01-01T10:00:00Z"
  }
}
```

## Update Team Member

```
PUT /api/v1/team-members/{id}
```

### Request Body

All fields are optional. Only provided fields are updated.

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Display name |
| `email` | string | Email address |
| `phone` | string | Phone number (E.164) |

### Example

```bash
curl -X PUT https://api.callmelater.io/v1/team-members/member-uuid-1 \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+15559999999"
  }'
```

### Response (200 OK)

```json
{
  "data": {
    "id": "member-uuid-1",
    "name": "John Smith",
    "email": "john@example.com",
    "phone": "+15559999999",
    "updated_at": "2025-01-08T11:00:00Z"
  }
}
```

## Delete Team Member

```
DELETE /api/v1/team-members/{id}
```

### Response (200 OK)

```json
{
  "message": "Team member deleted"
}
```

## Using Team Members in Reminders

Instead of specifying raw contact details, use team member IDs:

```json
{
  "mode": "gated",
  "gate": {
    "message": "Please approve deployment",
    "recipients": ["member-uuid-1", "member-uuid-2"],
    "channels": ["email", "sms"]
  }
}
```

The system will:
1. Look up each team member's contact details
2. Send to the appropriate channel based on available contact info
3. Track responses with team member attribution

## Error Responses

### Validation Error (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["At least one contact method (email or phone) is required."],
    "phone": ["The phone field must be a valid E.164 phone number."]
  }
}
```

### Not Found (404)

```json
{
  "message": "Team member not found"
}
```

### Duplicate Contact (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["This email is already used by another team member in this account."]
  }
}
```
