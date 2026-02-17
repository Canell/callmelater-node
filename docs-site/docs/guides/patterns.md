---
sidebar_position: 1
---

# Common Patterns

Practical, copy-paste-ready patterns for the most frequent CallMeLater use cases. Each example shows the SDK code alongside the equivalent curl request.

---

## Trial Expiration

Schedule a webhook to fire when a user's trial ends. If they subscribe before the timer runs out, cancel it by idempotency key.

import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

**Schedule the expiration webhook:**

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
import { CallMeLater } from 'callmelater';

const client = new CallMeLater({ apiToken: 'sk_live_...' });

const action = await client.http('https://api.example.com/subscriptions/expire')
  .post()
  .payload({ user_id: 123, action: 'downgrade_to_free' })
  .inDays(14)
  .idempotencyKey('trial:user:123')
  .send();

console.log(action.id); // act_...
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
use CallMeLater\Laravel\Facades\CallMeLater;

CallMeLater::http('https://api.example.com/subscriptions/expire')
    ->post()
    ->payload(['user_id' => 123, 'action' => 'downgrade_to_free'])
    ->inDays(14)
    ->idempotencyKey('trial:user:123')
    ->send();
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
curl -X POST https://callmelater.io/api/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "webhook",
    "idempotency_key": "trial:user:123",
    "schedule": { "wait": "14d" },
    "request": {
      "method": "POST",
      "url": "https://api.example.com/subscriptions/expire",
      "body": { "user_id": 123, "action": "downgrade_to_free" }
    },
    "max_attempts": 5
  }'
```

</TabItem>
</Tabs>

**Cancel if the user subscribes:**

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
await client.cancelAction({ idempotency_key: 'trial:user:123' });
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
// Cancel by idempotency key via the API
$client = app(\CallMeLater\Laravel\CallMeLater::class);
$client->cancel('trial:user:123'); // pass idempotency key
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
curl -X DELETE https://callmelater.io/api/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{ "idempotency_key": "trial:user:123" }'
```

</TabItem>
</Tabs>

**Tips:** Use `max_attempts: 5` -- this webhook matters. Make your endpoint idempotent so duplicate deliveries are harmless.

---

## Deployment Approval

Get human sign-off before deploying to production. The approval is sent immediately and the callback URL receives the response.

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
await client.reminder('Production deploy v2.4.1')
  .to('tech-lead@example.com')
  .toMany(['devops@example.com'])
  .message('Approve deployment of v2.4.1 to production?')
  .buttons('Approve', 'Reject')
  .noSnooze()
  .expiresInDays(1)
  .escalateTo(['cto@example.com'], 2)
  .inMinutes(0)
  .callback('https://ci.example.com/webhooks/approval')
  .idempotencyKey('deploy:v2.4.1:prod')
  .send();
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
CallMeLater::reminder('Production deploy v2.4.1')
    ->to('tech-lead@example.com')
    ->toMany(['devops@example.com'])
    ->message('Approve deployment of v2.4.1 to production?')
    ->buttons('Approve', 'Reject')
    ->noSnooze()
    ->expiresInDays(1)
    ->escalateTo(['cto@example.com'], afterHours: 2)
    ->inMinutes(0)
    ->callback('https://ci.example.com/webhooks/approval')
    ->idempotencyKey('deploy:v2.4.1:prod')
    ->send();
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
curl -X POST https://callmelater.io/api/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "approval",
    "name": "Production deploy v2.4.1",
    "idempotency_key": "deploy:v2.4.1:prod",
    "schedule": { "wait": "0m" },
    "gate": {
      "message": "Approve deployment of v2.4.1 to production?",
      "recipients": ["tech-lead@example.com", "devops@example.com"],
      "max_snoozes": 0,
      "timeout": "1d",
      "escalation": {
        "contacts": ["cto@example.com"],
        "after_hours": 2
      }
    },
    "callback_url": "https://ci.example.com/webhooks/approval"
  }'
```

</TabItem>
</Tabs>

**Handle the callback** to continue (or abort) your CI pipeline based on the response:

```ts
// In your callback handler
app.post('/webhooks/approval', (req, res) => {
  const { event, payload } = req.body;
  if (event === 'reminder.responded' && payload.response === 'confirmed') {
    triggerDeploy('v2.4.1', 'production');
  }
  res.sendStatus(200);
});
```

---

## Delayed Cleanup

Delete temporary resources (exports, uploads, sandbox data) after a retention period. Schedule the cleanup when you create the resource.

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
await client.http('https://api.example.com/exports/exp_abc123')
  .delete()
  .inDays(30)
  .idempotencyKey('cleanup:export:exp_abc123')
  .noRetry()
  .send();
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
CallMeLater::http('https://api.example.com/exports/exp_abc123')
    ->delete()
    ->inDays(30)
    ->idempotencyKey('cleanup:export:exp_abc123')
    ->noRetry()
    ->send();
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
curl -X POST https://callmelater.io/api/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "webhook",
    "idempotency_key": "cleanup:export:exp_abc123",
    "schedule": { "wait": "30d" },
    "request": {
      "method": "DELETE",
      "url": "https://api.example.com/exports/exp_abc123"
    },
    "max_attempts": 1
  }'
```

</TabItem>
</Tabs>

**Tips:** Use `noRetry()` / `max_attempts: 1` -- if the resource was already deleted manually, one failed attempt is enough. Include the resource ID in the idempotency key for easy cancellation.

---

## Scheduled Reports

Generate a weekly report every Monday morning. When the webhook fires, re-schedule the next one from your callback handler.

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
await client.http('https://api.example.com/reports/generate')
  .post()
  .payload({ type: 'weekly_summary', week: '2026-W08' })
  .at('next_monday')
  .timezone('America/New_York')
  .idempotencyKey('report:weekly:2026-W08')
  .callback('https://api.example.com/callbacks/report-done')
  .send();
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
CallMeLater::http('https://api.example.com/reports/generate')
    ->post()
    ->payload(['type' => 'weekly_summary', 'week' => '2026-W08'])
    ->at('next_monday')
    ->timezone('America/New_York')
    ->idempotencyKey('report:weekly:2026-W08')
    ->callback('https://api.example.com/callbacks/report-done')
    ->send();
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
curl -X POST https://callmelater.io/api/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "webhook",
    "idempotency_key": "report:weekly:2026-W08",
    "schedule": { "preset": "next_monday" },
    "timezone": "America/New_York",
    "request": {
      "method": "POST",
      "url": "https://api.example.com/reports/generate",
      "body": { "type": "weekly_summary", "week": "2026-W08" }
    },
    "callback_url": "https://api.example.com/callbacks/report-done"
  }'
```

</TabItem>
</Tabs>

**Re-schedule from your callback:**

```ts
// When the report webhook completes, schedule the next week
app.post('/callbacks/report-done', async (req, res) => {
  if (req.body.event === 'action.executed') {
    const nextWeek = getNextWeekString(); // e.g. "2026-W09"
    await client.http('https://api.example.com/reports/generate')
      .post()
      .payload({ type: 'weekly_summary', week: nextWeek })
      .at('next_monday')
      .timezone('America/New_York')
      .idempotencyKey(`report:weekly:${nextWeek}`)
      .callback('https://api.example.com/callbacks/report-done')
      .send();
  }
  res.sendStatus(200);
});
```

**Tips:** Include the week in the idempotency key to prevent duplicates. Set `timezone` so "Monday morning" stays consistent across DST changes.

---

## Follow-Up Sequence

Send an onboarding email series at day 1, day 3, and day 7 after signup. Each email gets its own action with a unique idempotency key so you can cancel the remaining ones if the user unsubscribes.

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
const userId = 123;
const steps = [
  { days: 1, template: 'welcome',         key: 'day1' },
  { days: 3, template: 'getting_started',  key: 'day3' },
  { days: 7, template: 'pro_tips',         key: 'day7' },
];

for (const step of steps) {
  await client.http('https://api.example.com/emails/send')
    .post()
    .payload({ user_id: userId, template: step.template })
    .inDays(step.days)
    .idempotencyKey(`onboard:user:${userId}:${step.key}`)
    .send();
}
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
$userId = 123;
$steps = [
    ['days' => 1, 'template' => 'welcome',        'key' => 'day1'],
    ['days' => 3, 'template' => 'getting_started', 'key' => 'day3'],
    ['days' => 7, 'template' => 'pro_tips',        'key' => 'day7'],
];

foreach ($steps as $step) {
    CallMeLater::http('https://api.example.com/emails/send')
        ->post()
        ->payload(['user_id' => $userId, 'template' => $step['template']])
        ->inDays($step['days'])
        ->idempotencyKey("onboard:user:{$userId}:{$step['key']}")
        ->send();
}
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
# Day 1 - Welcome email
curl -X POST https://callmelater.io/api/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "idempotency_key": "onboard:user:123:day1",
    "schedule": { "wait": "1d" },
    "request": {
      "method": "POST",
      "url": "https://api.example.com/emails/send",
      "body": { "user_id": 123, "template": "welcome" }
    }
  }'

# Day 3 - Getting started
curl -X POST https://callmelater.io/api/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "idempotency_key": "onboard:user:123:day3",
    "schedule": { "wait": "3d" },
    "request": {
      "method": "POST",
      "url": "https://api.example.com/emails/send",
      "body": { "user_id": 123, "template": "getting_started" }
    }
  }'

# Day 7 - Pro tips
curl -X POST https://callmelater.io/api/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "idempotency_key": "onboard:user:123:day7",
    "schedule": { "wait": "7d" },
    "request": {
      "method": "POST",
      "url": "https://api.example.com/emails/send",
      "body": { "user_id": 123, "template": "pro_tips" }
    }
  }'
```

</TabItem>
</Tabs>

**Cancel remaining emails if the user unsubscribes:**

```ts
// Cancel all pending onboarding emails for a user
for (const key of ['day1', 'day3', 'day7']) {
  await client.cancelAction({ idempotency_key: `onboard:user:${userId}:${key}` });
}
```

---

## Invoice Reminder with Escalation

Remind a customer about an upcoming payment. If they do not respond within 48 hours, escalate to your accounts receivable team.

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
await client.reminder('Invoice INV-2026-042 payment due')
  .to('billing@customer.com')
  .message(
    'Invoice INV-2026-042 ($3,200) is due in 5 days. ' +
    'Click Confirm to mark as scheduled for payment.'
  )
  .buttons('Confirm Payment', 'Decline')
  .allowSnooze(2)
  .expiresInDays(7)
  .escalateTo(['ar@yourcompany.com', '+15551234567'], 48)
  .at('2026-02-20T09:00:00')
  .timezone('America/Chicago')
  .idempotencyKey('invoice:INV-2026-042:reminder')
  .callback('https://api.example.com/webhooks/invoice-response')
  .send();
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
CallMeLater::reminder('Invoice INV-2026-042 payment due')
    ->to('billing@customer.com')
    ->message(
        'Invoice INV-2026-042 ($3,200) is due in 5 days. '
        . 'Click Confirm to mark as scheduled for payment.'
    )
    ->buttons('Confirm Payment', 'Decline')
    ->allowSnooze(2)
    ->expiresInDays(7)
    ->escalateTo(['ar@yourcompany.com', '+15551234567'], afterHours: 48)
    ->at('2026-02-20T09:00:00')
    ->timezone('America/Chicago')
    ->idempotencyKey('invoice:INV-2026-042:reminder')
    ->callback('https://api.example.com/webhooks/invoice-response')
    ->send();
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
curl -X POST https://callmelater.io/api/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "mode": "approval",
    "name": "Invoice INV-2026-042 payment due",
    "idempotency_key": "invoice:INV-2026-042:reminder",
    "scheduled_for": "2026-02-20T09:00:00",
    "timezone": "America/Chicago",
    "gate": {
      "message": "Invoice INV-2026-042 ($3,200) is due in 5 days. Click Confirm to mark as scheduled for payment.",
      "recipients": ["billing@customer.com"],
      "max_snoozes": 2,
      "timeout": "7d",
      "escalation": {
        "contacts": ["ar@yourcompany.com", "+15551234567"],
        "after_hours": 48
      }
    },
    "callback_url": "https://api.example.com/webhooks/invoice-response"
  }'
```

</TabItem>
</Tabs>

**Tips:** Schedule the reminder a few days before the due date using `scheduled_for`. Set `escalation.after_hours` based on urgency -- 48 hours gives the customer time to respond before your team gets involved. Include a phone number in escalation contacts for SMS notifications.
