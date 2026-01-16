---
sidebar_position: 1
---

# Trial Expiration Workflow

A common use case: notify your system when a user's trial ends.

## The Problem

You want to:
1. Let users try your product for 14 days
2. Automatically downgrade or notify them when the trial ends
3. Handle the timing reliably, even if your servers restart

## The Solution

Schedule an HTTP action when the trial starts:

```bash
curl -X POST https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "idempotency_key": "trial-end-user-42",
    "intent": {
      "delay": "14d"
    },
    "http_request": {
      "url": "https://your-app.com/webhooks/trial-expired",
      "body": {
        "event": "trial_expired",
        "user_id": 42,
        "action": "downgrade_to_free"
      }
    }
  }'
```

:::tip Minimal payload
The `type` field defaults to `http`, and the request will be signed with your account's webhook secret automatically.
:::

## Handle the Webhook

```javascript
app.post('/webhooks/trial-expired', (req, res) => {
  // Verify signature
  if (!verifySignature(req)) {
    return res.status(401).send('Invalid signature');
  }

  const { user_id, action } = req.body;

  // Process based on action
  if (action === 'downgrade_to_free') {
    await downgradeUser(user_id);
    await sendTrialEndedEmail(user_id);
  }

  res.status(200).send('OK');
});
```

## Cancel if They Subscribe

If the user subscribes before the trial ends, cancel the action:

```bash
curl -X DELETE https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "idempotency_key": "trial-end-user-42"
  }'
```

## Why This Works

1. **Reliable timing** — CallMeLater handles the delay, not your cron jobs
2. **Survives restarts** — The action is stored durably
3. **Easy cancellation** — Use the idempotency key to cancel
4. **Retries on failure** — If your webhook fails, we retry automatically
5. **Full visibility** — See exactly what happened in the dashboard
