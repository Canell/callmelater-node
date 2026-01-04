---
sidebar_position: 2
---

# Approval Workflows

Use reminders to get human confirmation before proceeding with an action.

## The Problem

Some actions need human approval:
- Production deployments
- Expense approvals
- Access requests
- Critical operations

You want someone to confirm before proceeding.

## Basic Approval

Send a reminder and wait for confirmation:

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "type": "reminder",
    "idempotency_key": "deploy-approval-v2.1.0",
    "intent": {
      "delay": "0m"
    },
    "reminder_message": "Please approve deployment of v2.1.0 to production",
    "recipients": [
      "tech-lead@example.com"
    ],
    "confirmation_mode": "any",
    "expires_after": "4h"
  }'
```

The recipient receives an email with:
- **Confirm** — Approve the deployment
- **Decline** — Reject the deployment
- **Snooze** — Review later

## Multi-Person Approval

Require everyone to approve:

```json
{
  "confirmation_mode": "all",
  "recipients": [
    "cto@example.com",
    "security@example.com",
    "ops@example.com"
  ]
}
```

All three must confirm before the action is marked as `executed`.

## Escalation

If no one responds, escalate to a manager:

```json
{
  "recipients": ["team@example.com"],
  "escalation_contacts": ["manager@example.com"],
  "escalate_after": "2h",
  "expires_after": "8h"
}
```

Timeline:
- 0h: Team receives reminder
- 2h: If no response, manager is notified
- 8h: If still no response, action fails

## Handle the Response

Poll for the action status or configure a webhook:

```javascript
// Check action status
const action = await callmelater.getAction('deploy-approval-v2.1.0');

if (action.resolution_status === 'executed') {
  // Approval granted
  await deployToProduction();
} else if (action.resolution_status === 'failed') {
  // Declined or expired
  await notifyDeploymentBlocked();
}
```

## Real-World Example: Deployment Pipeline

```javascript
async function requestDeploymentApproval(version, environment) {
  // Create approval request
  const action = await callmelater.createAction({
    type: 'reminder',
    idempotency_key: `deploy-${version}-${environment}`,
    intent: { delay: '0m' },
    reminder_message: `Approve deployment of ${version} to ${environment}?`,
    recipients: getApprovers(environment),
    confirmation_mode: environment === 'production' ? 'all' : 'any',
    expires_after: '4h'
  });

  // Wait for response (in practice, use webhooks)
  return waitForApproval(action.id);
}

// In your CI/CD pipeline
const approved = await requestDeploymentApproval('v2.1.0', 'production');
if (approved) {
  await deploy();
}
```
