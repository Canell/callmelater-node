---
sidebar_position: 1
slug: /
---

# CallMeLater

Schedule durable HTTP calls and interactive human approvals. One API, automatic retries, full audit trail.

## What it does

- **Scheduled webhooks** — Fire any HTTP request minutes, days, or months from now. Retries automatically on failure.
- **Human approvals** — Send Yes/No/Snooze approval requests via email, SMS, Teams, or Slack. Get notified when someone responds.
- **Multi-step workflows** — Chain webhooks, approvals, and wait steps into sequential workflows with data passing.

:::info What CallMeLater is NOT
It's not a cron scheduler, a message queue, or a workflow engine. It schedules discrete future actions and delivers them reliably.
:::

## Choose your path

import Link from '@docusaurus/Link';

<div className="row margin-top--lg">
  <div className="col col--4 margin-bottom--lg">
    <div className="card padding--lg">
      <h3>Quick Start</h3>
      <p>Schedule your first action in 60 seconds with curl.</p>
      <Link to="/quick-start">Get started →</Link>
    </div>
  </div>
  <div className="col col--4 margin-bottom--lg">
    <div className="card padding--lg">
      <h3>Node.js SDK</h3>
      <p>TypeScript, zero dependencies, fluent API.</p>
      <Link to="/sdks/nodejs">Install SDK →</Link>
    </div>
  </div>
  <div className="col col--4 margin-bottom--lg">
    <div className="card padding--lg">
      <h3>Laravel SDK</h3>
      <p>Facades, fluent builders, webhook events.</p>
      <Link to="/sdks/laravel">Install SDK →</Link>
    </div>
  </div>
  <div className="col col--4 margin-bottom--lg">
    <div className="card padding--lg">
      <h3>n8n Integration</h3>
      <p>Visual workflows with trigger and action nodes.</p>
      <Link to="/sdks/n8n">Set up n8n →</Link>
    </div>
  </div>
  <div className="col col--4 margin-bottom--lg">
    <div className="card padding--lg">
      <h3>API Reference</h3>
      <p>Full endpoint documentation for direct HTTP usage.</p>
      <Link to="/api/authentication">View API docs →</Link>
    </div>
  </div>
</div>
