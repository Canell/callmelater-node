# n8n-nodes-callmelater

This is an n8n community node package for [CallMeLater](https://callmelater.io) - the durable scheduling service for webhooks and human approvals.

## Features

- **Schedule Webhooks**: Create scheduled HTTP requests that execute reliably with automatic retries
- **Human Approvals**: Send approval requests via email, SMS, Teams, or Slack and wait for responses
- **Event Triggers**: React to reminder responses, action completions, and failures in your n8n workflows

## Installation

### In n8n Desktop/Cloud

1. Go to **Settings** → **Community Nodes**
2. Select **Install a community node**
3. Enter `n8n-nodes-callmelater`
4. Click **Install**

### In Self-Hosted n8n

```bash
npm install n8n-nodes-callmelater
```

Then restart your n8n instance.

## Credentials

1. Sign up at [callmelater.io](https://callmelater.io)
2. Go to **Settings** → **API Tokens**
3. Create a new token with `read` and `write` abilities
4. In n8n, create new **CallMeLater API** credentials with your token

## Nodes

### CallMeLater

Actions you can perform:

| Operation | Description |
|-----------|-------------|
| Create Webhook | Schedule an HTTP request to execute at a specific time |
| Create Approval | Send an approval request to recipients |
| Get Action | Retrieve details of an existing action |
| Cancel Action | Cancel a scheduled action |

### CallMeLater Trigger

Triggers your workflow when CallMeLater events occur:

| Event | Description |
|-------|-------------|
| Reminder Responded | Someone confirmed, declined, or snoozed |
| Action Executed | A scheduled webhook completed successfully |
| Action Failed | A webhook failed after all retries |
| Action Expired | A reminder expired without response |

## Example Workflows

### Deployment Approval

```
GitHub Trigger → Build & Test → CallMeLater (Create Approval) → CallMeLater Trigger → Deploy
```

### Scheduled Follow-up

```
New Customer → CallMeLater (Wait 3 days) → Send Welcome Email
```

### Invoice Reminder with Escalation

```
Invoice Created → CallMeLater (Wait 7 days) → Check Payment → If Unpaid: CallMeLater (Approval to Manager)
```

## Configuration

### Webhook URL Setup

For the **CallMeLater Trigger** to work:

1. In n8n, create a workflow with the CallMeLater Trigger node
2. Copy the **Webhook URL** shown in the node
3. When creating actions via the CallMeLater node, paste this URL in the **Callback URL** field

### Webhook Signature Verification

To verify that webhooks are genuinely from CallMeLater:

1. Generate a secret (any random string)
2. Add it to your CallMeLater action as `webhook_secret`
3. Add the same secret to the CallMeLater Trigger's **Webhook Secret** field

## Resources

- [CallMeLater Documentation](https://docs.callmelater.io)
- [API Reference](https://docs.callmelater.io/api)
- [n8n Community Nodes](https://docs.n8n.io/integrations/community-nodes/)

## License

MIT
