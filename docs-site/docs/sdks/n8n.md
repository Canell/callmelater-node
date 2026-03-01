---
sidebar_position: 3
---

# n8n Integration

Use CallMeLater in n8n visual workflows with the community node package.

## Installation

### n8n Desktop / Cloud

1. Go to **Settings** → **Community Nodes**
2. Click **Install a community node**
3. Enter `n8n-nodes-callmelater`
4. Click **Install**

### Self-hosted n8n

```bash
npm install n8n-nodes-callmelater
```

Restart your n8n instance after installing.

## Credentials Setup

1. Sign up at [callmelater.io](https://callmelater.io)
2. Go to **Settings → API Tokens** and create a token with `read` + `write` scopes
3. In n8n, create **CallMeLater API** credentials with your token

## Nodes

### CallMeLater (Action Node)

| Operation | Description |
|-----------|-------------|
| Create Webhook | Schedule an HTTP request for later |
| Create Approval | Send an approval request to recipients |
| Get Action | Retrieve details of an action |
| Cancel Action | Cancel a scheduled action |

### CallMeLater Trigger

Starts your workflow when CallMeLater events occur:

| Event | Description |
|-------|-------------|
| Reminder Responded | Someone confirmed, declined, or snoozed |
| Action Executed | A webhook completed successfully |
| Action Failed | A webhook failed after all retries |
| Action Expired | A reminder expired without response |

## Example Workflows

### Deployment approval

```
GitHub Trigger → Build & Test → CallMeLater (Create Approval) → CallMeLater Trigger → Deploy
```

1. GitHub push triggers the workflow
2. Build and test steps run
3. CallMeLater sends approval request to the ops team
4. When someone approves, the trigger fires and deployment continues

### Recurring health checks

```
Schedule Trigger → CallMeLater (Create Webhook, repeat every 1h) → CallMeLater Trigger → Alert if unhealthy
```

Use the `recurrence` field in the API body to create recurring actions from n8n. Set `recurrence.frequency`, `recurrence.unit`, and `recurrence.end_type` in the request body.

### Scheduled follow-up

```
New Customer → CallMeLater (Wait 3 days) → Send Welcome Email
```

### Invoice reminder with escalation

```
Invoice Created → CallMeLater (Wait 7 days) → Check Payment → If Unpaid: CallMeLater (Approval to Manager)
```

## Webhook Setup

For the **CallMeLater Trigger** to receive events:

1. Create a workflow with the CallMeLater Trigger node
2. Copy the **Webhook URL** shown in the node
3. When creating actions via the CallMeLater node, paste this URL as the **Callback URL**

### Signature verification

1. Generate a random secret string
2. Set it as `webhook_secret` when creating actions
3. Enter the same secret in the CallMeLater Trigger node's **Webhook Secret** field
