---
sidebar_position: 3
---

# Templates

Templates are reusable action configurations with unique trigger URLs. Once created, anyone can trigger a template with a simple POST request -- no API key required. This makes templates ideal for CI/CD pipelines, external integrations, and shared workflows.

## What Templates Are

A template captures an action's configuration (URL, method, body, approval settings, retry policy) and exposes it behind a public trigger URL. When triggered, the template creates a new action (or chain) with the stored configuration, substituting any placeholder values provided in the request.

**Key properties:**

- Each template gets a unique trigger token and URL (e.g., `https://callmelater.io/t/clmt_abc123...`)
- Triggering does not require an API key -- the token is the authentication
- Placeholders let you inject dynamic values at trigger time
- Templates can create single actions (webhook or approval) or entire chains

---

## Creating a Template

This example creates a deployment notification template with placeholders for the service name, version, and environment.

import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
import { CallMeLater } from 'callmelater';

const client = new CallMeLater({ apiToken: 'sk_live_...' });

const template = await client.template('Deploy {{service}}')
  .description('Triggers a deployment approval workflow')
  .type('action')
  .mode('approval')
  .timezone('America/New_York')
  .gateConfig({
    message: 'Deploy {{service}} v{{version}} to {{env}}?',
    recipients: ['ops@example.com'],
    timeout: '4h',
    on_timeout: 'cancel',
  })
  .requestConfig({
    url: 'https://deploy.example.com/{{service}}',
    method: 'POST',
    body: {
      version: '{{version}}',
      environment: '{{env}}',
    },
  })
  .placeholder('service', true, 'Service name to deploy')
  .placeholder('version', true, 'Semantic version number')
  .placeholder('env', false, 'Target environment', 'staging')
  .maxAttempts(3)
  .retryStrategy('exponential')
  .send();

console.log(template.trigger_url);
// https://callmelater.io/t/clmt_abc123...
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
use CallMeLater\Laravel\Facades\CallMeLater;

$template = CallMeLater::template('Deploy {{service}}')
    ->description('Triggers a deployment approval workflow')
    ->type('action')
    ->mode('approval')
    ->timezone('America/New_York')
    ->gateConfig([
        'message' => 'Deploy {{service}} v{{version}} to {{env}}?',
        'recipients' => ['ops@example.com'],
        'timeout' => '4h',
        'on_timeout' => 'cancel',
    ])
    ->requestConfig([
        'url' => 'https://deploy.example.com/{{service}}',
        'method' => 'POST',
        'body' => [
            'version' => '{{version}}',
            'environment' => '{{env}}',
        ],
    ])
    ->placeholder('service', required: true, description: 'Service name to deploy')
    ->placeholder('version', required: true, description: 'Semantic version number')
    ->placeholder('env', required: false, description: 'Target environment', default: 'staging')
    ->maxAttempts(3)
    ->retryStrategy('exponential')
    ->send();

echo $template['trigger_url'];
// https://callmelater.io/t/clmt_abc123...
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
curl -X POST https://callmelater.io/api/v1/templates \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Deploy {{service}}",
    "description": "Triggers a deployment approval workflow",
    "type": "action",
    "mode": "approval",
    "timezone": "America/New_York",
    "gate_config": {
      "message": "Deploy {{service}} v{{version}} to {{env}}?",
      "recipients": ["ops@example.com"],
      "timeout": "4h",
      "on_timeout": "cancel"
    },
    "request_config": {
      "url": "https://deploy.example.com/{{service}}",
      "method": "POST",
      "body": {
        "version": "{{version}}",
        "environment": "{{env}}"
      }
    },
    "placeholders": [
      { "name": "service", "required": true, "description": "Service name to deploy" },
      { "name": "version", "required": true, "description": "Semantic version number" },
      { "name": "env", "required": false, "default": "staging", "description": "Target environment" }
    ],
    "max_attempts": 3,
    "retry_strategy": "exponential"
  }'
```

</TabItem>
</Tabs>

---

## Placeholders

Placeholders let you inject dynamic values into templates at trigger time. They use the `{{placeholder_name}}` syntax.

### Defining placeholders

Each placeholder has these properties:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Variable name (alphanumeric and underscore) |
| `required` | boolean | No | Whether the value must be provided (default: `false`) |
| `description` | string | No | Human-readable description |
| `default` | any | No | Default value when not provided at trigger time |

### Where placeholders work

Placeholders are resolved in all of these fields:

- **Template name** -- `Deploy {{service}}`
- **Request URL** -- `https://api.example.com/{{service}}/deploy`
- **Request body** -- `{ "version": "{{version}}" }`
- **Request headers** -- `{ "Authorization": "Bearer {{api_token}}" }`
- **Gate message** -- `Approve deployment of {{service}}?`
- **Gate recipients** -- `["email:{{approver_email}}"]`
- **Dedup keys** -- `["deploy:{{service}}:{{env}}"]`

If a required placeholder is missing from the trigger request, the API returns a `422` validation error.

---

## Triggering

Trigger a template by sending a POST request to its public URL. No API key is needed -- the trigger token embedded in the URL serves as authentication.

### Basic trigger

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
await client.trigger('clmt_abc123...', {
  service: 'api-gateway',
  version: '2.4.1',
  env: 'production',
});
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
CallMeLater::trigger('clmt_abc123...', [
    'service' => 'api-gateway',
    'version' => '2.4.1',
    'env' => 'production',
]);
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
curl -X POST https://callmelater.io/t/clmt_abc123... \
  -H "Content-Type: application/json" \
  -d '{
    "service": "api-gateway",
    "version": "2.4.1",
    "env": "production"
  }'
```

</TabItem>
</Tabs>

### From a GitHub Actions workflow

Since no API key is needed, templates are perfect for CI/CD. Add the trigger URL as a repository secret and call it from your workflow:

```yaml
# .github/workflows/deploy.yml
name: Deploy
on:
  push:
    tags: ['v*']

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Request deployment approval
        run: |
          curl -X POST ${{ secrets.CALLMELATER_DEPLOY_URL }} \
            -H "Content-Type: application/json" \
            -d '{
              "service": "api-gateway",
              "version": "${{ github.ref_name }}",
              "env": "production"
            }'
```

---

## Chain Templates

Templates can create multi-step chains instead of single actions. Set `type: "chain"` and define `chain_steps` instead of `request_config`.

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
const chainTemplate = await client.template('Onboard {{user_email}}')
  .type('chain')
  .chainSteps([
    {
      name: 'Create Account',
      type: 'webhook',
      url: 'https://api.example.com/users',
      method: 'POST',
      body: { email: '{{user_email}}' },
    },
    {
      name: 'Wait for provisioning',
      type: 'wait',
      wait: '5m',
    },
    {
      name: 'Manager Approval',
      type: 'approval',
      gate: {
        message: 'Approve new account for {{user_email}}?',
        recipients: ['{{manager_email}}'],
      },
    },
    {
      name: 'Send Welcome',
      type: 'webhook',
      url: 'https://api.example.com/welcome',
      method: 'POST',
      body: { email: '{{user_email}}', user_id: '{{steps.0.response.id}}' },
      condition: "{{steps.2.status}} == confirmed",
    },
  ])
  .chainErrorHandling('fail_chain')
  .placeholder('user_email', true, 'New user email')
  .placeholder('manager_email', true, 'Approving manager email')
  .send();
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
$chainTemplate = CallMeLater::template('Onboard {{user_email}}')
    ->type('chain')
    ->chainSteps([
        [
            'name' => 'Create Account',
            'type' => 'webhook',
            'url' => 'https://api.example.com/users',
            'method' => 'POST',
            'body' => ['email' => '{{user_email}}'],
        ],
        [
            'name' => 'Wait for provisioning',
            'type' => 'wait',
            'wait' => '5m',
        ],
        [
            'name' => 'Manager Approval',
            'type' => 'approval',
            'gate' => [
                'message' => 'Approve new account for {{user_email}}?',
                'recipients' => ['{{manager_email}}'],
            ],
        ],
        [
            'name' => 'Send Welcome',
            'type' => 'webhook',
            'url' => 'https://api.example.com/welcome',
            'method' => 'POST',
            'body' => ['email' => '{{user_email}}', 'user_id' => '{{steps.0.response.id}}'],
            'condition' => '{{steps.2.status}} == confirmed',
        ],
    ])
    ->chainErrorHandling('fail_chain')
    ->placeholder('user_email', required: true, description: 'New user email')
    ->placeholder('manager_email', required: true, description: 'Approving manager email')
    ->send();
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
curl -X POST https://callmelater.io/api/v1/templates \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Onboard {{user_email}}",
    "type": "chain",
    "chain_steps": [
      {
        "name": "Create Account",
        "type": "http_call",
        "url": "https://api.example.com/users",
        "method": "POST",
        "body": { "email": "{{user_email}}" }
      },
      {
        "name": "Wait for provisioning",
        "type": "delay",
        "delay": "5m"
      },
      {
        "name": "Manager Approval",
        "type": "gated",
        "gate": {
          "message": "Approve new account for {{user_email}}?",
          "recipients": ["{{manager_email}}"]
        }
      },
      {
        "name": "Send Welcome",
        "type": "http_call",
        "url": "https://api.example.com/welcome",
        "method": "POST",
        "body": { "email": "{{user_email}}", "user_id": "{{steps.0.response.id}}" },
        "condition": "{{steps.2.status}} == confirmed"
      }
    ],
    "chain_error_handling": "fail_chain",
    "placeholders": [
      { "name": "user_email", "required": true, "description": "New user email" },
      { "name": "manager_email", "required": true, "description": "Approving manager email" }
    ]
  }'
```

</TabItem>
</Tabs>

Triggering a chain template returns a chain object instead of a single action. Placeholders and `{{steps.N.response.*}}` interpolation work the same way.

---

## Management

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
// Toggle active/inactive
await client.toggleTemplate('tpl_abc123');

// Regenerate trigger token (old URL stops working immediately)
const updated = await client.regenerateTemplateToken('tpl_abc123');
console.log(updated.trigger_url); // new URL

// Update template configuration
await client.template('Deploy {{service}} v2')
  .description('Updated deployment template')
  .maxAttempts(5)
  .update('tpl_abc123');

// Delete template
await client.deleteTemplate('tpl_abc123');

// Check quota
const limits = await client.templateLimits();
console.log(`${limits.remaining} of ${limits.max} templates remaining`);
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
// Toggle active/inactive
CallMeLater::toggleTemplate('tpl_abc123');

// Regenerate trigger token (old URL stops working immediately)
$updated = CallMeLater::regenerateTemplateToken('tpl_abc123');

// Update template configuration
CallMeLater::template('Deploy {{service}} v2')
    ->description('Updated deployment template')
    ->maxAttempts(5)
    ->update('tpl_abc123');

// Delete template
CallMeLater::deleteTemplate('tpl_abc123');

// Check quota
$limits = CallMeLater::templateLimits();
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
# Toggle active/inactive
curl -X POST https://callmelater.io/api/v1/templates/tpl_abc123/toggle-active \
  -H "Authorization: Bearer sk_live_..."

# Regenerate trigger token
curl -X POST https://callmelater.io/api/v1/templates/tpl_abc123/regenerate-token \
  -H "Authorization: Bearer sk_live_..."

# Update template
curl -X PUT https://callmelater.io/api/v1/templates/tpl_abc123 \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{ "description": "Updated deployment template", "max_attempts": 5 }'

# Delete template
curl -X DELETE https://callmelater.io/api/v1/templates/tpl_abc123 \
  -H "Authorization: Bearer sk_live_..."

# Check quota
curl https://callmelater.io/api/v1/templates/limits \
  -H "Authorization: Bearer sk_live_..."
```

</TabItem>
</Tabs>

:::warning
Regenerating a trigger token invalidates the previous URL immediately. Any CI/CD pipelines or external systems using the old URL will start receiving 404 errors. Update all references before rotating tokens.
:::

---

## Rate Limits

Template triggers are rate-limited to prevent abuse:

| Scope | Limit |
|-------|-------|
| Per trigger token | 60 requests/minute |
| Per IP address | 120 requests/minute |

Exceeding these limits returns a `429 Too Many Requests` response. The `Retry-After` header indicates how many seconds to wait.
