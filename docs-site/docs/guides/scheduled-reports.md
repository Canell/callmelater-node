---
sidebar_position: 3
---

# Scheduled Reports

Trigger report generation at specific times.

## Daily Reports

Schedule a daily summary to be generated every morning:

```javascript
async function scheduleDailyReport(userId) {
  await callmelater.createAction({
    idempotency_key: `daily-report-${userId}-${today()}`,
    intent: {
      preset: 'tomorrow',
      timezone: 'America/New_York'
    },
    request: {
      method: 'POST',
      url: 'https://your-app.com/webhooks/generate-report',
      body: {
        type: 'daily_summary',
        user_id: userId,
        date: today()
      }
    }
  });
}
```

## Weekly Reports

Send every Monday at 9 AM:

```json
{
  "intent": {
    "preset": "next_monday",
    "timezone": "Europe/London"
  }
}
```

## Recurring Pattern

CallMeLater doesn't have built-in recurrence. Instead, schedule the next report when the current one completes:

```javascript
app.post('/webhooks/generate-report', async (req, res) => {
  const { type, user_id, date } = req.body;

  // Generate the report
  await generateReport(type, user_id, date);

  // Schedule the next one
  if (type === 'daily_summary') {
    await scheduleDailyReport(user_id);
  }

  res.status(200).send('OK');
});
```

This pattern ensures:
- Reports are generated reliably
- If generation fails, the retry will reschedule
- You have full control over the recurrence logic

## End-of-Month Reports

Calculate the last day of the month:

```javascript
function scheduleMonthlyReport(userId) {
  const lastDay = new Date(
    new Date().getFullYear(),
    new Date().getMonth() + 1,
    0
  );

  return callmelater.createAction({
    idempotency_key: `monthly-${userId}-${lastDay.toISOString().slice(0, 7)}`,
    intent: {
      execute_at: lastDay.toISOString()
    },
    request: {
      url: 'https://your-app.com/webhooks/generate-report',
      body: {
        type: 'monthly_summary',
        user_id: userId
      }
    }
  });
}
```

## Best Practices

1. **Use idempotency keys with dates** — Prevents duplicate reports for the same period

2. **Handle timezone correctly** — Use the user's timezone for presets

3. **Chain schedules** — Schedule the next occurrence when the current one succeeds

4. **Use retries** — Report generation can fail; let CallMeLater retry
