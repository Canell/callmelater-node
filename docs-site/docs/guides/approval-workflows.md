---
sidebar_position: 2
---

# Approval Workflows

Use gated actions to get human confirmation before proceeding with an action.

:::info How Approval Works
Approval workflows are implemented by creating a **gated** action. The reminder *is* the approval request.

Your system is responsible for executing the follow-up logic (e.g., deploying, calling an API) once approval is confirmed. CallMeLater notifies you of the response — it does not automatically chain actions together.
:::

## The Problem

Some actions need human approval:
- Production deployments
- Expense approvals
- Access requests
- Critical operations

You want someone to confirm before proceeding.

## Basic Approval

Send a gated action and wait for confirmation:

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "gated",
    "idempotency_key": "deploy-approval-v2.1.0",
    "intent": {
      "delay": "0m"
    },
    "gate": {
      "message": "Please approve deployment of v2.1.0 to production",
      "recipients": ["tech-lead@example.com"],
      "token_expiry_days": 1,
      "confirmation_mode": "first_response"
    }
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
  "mode": "gated",
  "gate": {
    "message": "Please approve the deployment",
    "confirmation_mode": "all_required",
    "recipients": [
      "cto@example.com",
      "security@example.com",
      "ops@example.com"
    ]
  }
}
```

All three must confirm before the action is marked as `executed`.

## Escalation

If no one responds, escalate to a manager:

```json
{
  "mode": "gated",
  "gate": {
    "message": "Please approve",
    "recipients": ["team@example.com"],
    "escalation_contacts": ["manager@example.com"],
    "escalate_after_hours": 2,
    "token_expiry_days": 1
  }
}
```

Timeline:
- 0h: Team receives reminder
- 2h: If no response, manager is notified
- 24h: If still no response, response links expire

## Handle the Response

Poll for the action status or configure a webhook:

```javascript
// Check action status
const action = await callmelater.getAction('deploy-approval-v2.1.0');

if (action.status === 'executed') {
  // Approval granted
  await deployToProduction();
} else if (action.status === 'failed') {
  // Declined or expired
  await notifyDeploymentBlocked();
}
```

## Real-World Example: Deployment Pipeline

```javascript
async function requestDeploymentApproval(version, environment) {
  // Create approval request
  const action = await callmelater.createAction({
    mode: 'gated',
    idempotency_key: `deploy-${version}-${environment}`,
    intent: { delay: '0m' },
    gate: {
      message: `Approve deployment of ${version} to ${environment}?`,
      recipients: getApprovers(environment),
      confirmation_mode: environment === 'production' ? 'all_required' : 'first_response'
    }
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
