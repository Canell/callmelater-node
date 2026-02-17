---
sidebar_position: 2
---

# Chains & Workflows

Chains let you compose multi-step workflows where each step can be an HTTP webhook, a human approval gate, or a timed wait. Steps execute sequentially, pass data forward, and handle failures as a unit.

## When to Use Chains

Use chains when you need steps that **depend on each other**. A chain shares context between steps -- the HTTP response from step 1 is available as a variable in step 3 -- and treats the whole sequence as a single unit for error handling.

Use **individual actions** when steps are independent and do not need shared state. For example, scheduling three unrelated webhooks is simpler as three separate actions.

| Feature | Individual Actions | Chains |
|---------|-------------------|--------|
| Steps depend on each other | No | Yes |
| Data passed between steps | No | Yes (`{{steps.N.response.*}}`) |
| Fail/cancel as a unit | No | Yes |
| Human approval mid-workflow | Separate action | Built-in gate step |

---

## Step Types

| Type | API Request Value | API Response Alias | Description | Use For |
|------|-------------------|-------------------|-------------|---------|
| **HTTP** | `http_call` | `webhook` | Makes an HTTP request and captures the response | API calls, webhooks, data processing |
| **Approval** | `gated` | `approval` | Sends a message to recipients and waits for a human response | Sign-offs, reviews, confirmations |
| **Wait** | `delay` | `wait` | Pauses the chain for a specified duration | Cooling periods, rate limiting, delays between steps |

In API requests, use `http_call`, `gated`, `delay`. API responses return the aliases `webhook`, `approval`, `wait`.

---

## Building a Chain

Here is a complete expense approval workflow with four steps: validate the expense, ask a manager to approve it, wait for a cooling period, then process the payment.

import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
import { CallMeLater } from 'callmelater';

const client = new CallMeLater({ apiToken: 'sk_live_...' });

await client.chain('Expense Approval')
  .input({ expense_id: 'exp_42', amount: 450.00, submitter: 'alice@example.com' })
  .errorHandling('fail_chain')

  // Step 0: Validate the expense
  .addHttpStep('Validate Expense')
    .url('https://api.example.com/expenses/validate')
    .post()
    .body({
      expense_id: '{{input.expense_id}}',
      amount: '{{input.amount}}'
    })
    .maxAttempts(3)
    .done()

  // Step 1: Manager approval
  .addGateStep('Manager Approval')
    .message('Approve expense of ${{input.amount}} submitted by {{input.submitter}}?')
    .to('manager@example.com')
    .timeout('48h')
    .onTimeout('cancel')
    .done()

  // Step 2: Cooling period
  .addDelayStep('Cooling Period')
    .hours(1)
    .done()

  // Step 3: Process payment (only if approved)
  .addHttpStep('Process Payment')
    .url('https://api.example.com/payments')
    .post()
    .body({
      expense_id: '{{input.expense_id}}',
      validation_ref: '{{steps.0.response.reference}}'
    })
    .condition("{{steps.1.status}} == confirmed")
    .done()

  .send();
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
use CallMeLater\Laravel\Facades\CallMeLater;

CallMeLater::chain('Expense Approval')
    ->input(['expense_id' => 'exp_42', 'amount' => 450.00, 'submitter' => 'alice@example.com'])
    ->errorHandling('fail_chain')

    // Step 0: Validate the expense
    ->addHttpStep('Validate Expense')
        ->url('https://api.example.com/expenses/validate')
        ->post()
        ->body([
            'expense_id' => '{{input.expense_id}}',
            'amount' => '{{input.amount}}',
        ])
        ->maxAttempts(3)
        ->done()

    // Step 1: Manager approval
    ->addGateStep('Manager Approval')
        ->message('Approve expense of ${{input.amount}} submitted by {{input.submitter}}?')
        ->to('manager@example.com')
        ->timeout('48h')
        ->onTimeout('cancel')
        ->done()

    // Step 2: Cooling period
    ->addDelayStep('Cooling Period')
        ->hours(1)
        ->done()

    // Step 3: Process payment (only if approved)
    ->addHttpStep('Process Payment')
        ->url('https://api.example.com/payments')
        ->post()
        ->body([
            'expense_id' => '{{input.expense_id}}',
            'validation_ref' => '{{steps.0.response.reference}}',
        ])
        ->condition('{{steps.1.status}} == confirmed')
        ->done()

    ->send();
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
curl -X POST https://callmelater.io/api/v1/chains \
  -H "Authorization: Bearer sk_live_..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Expense Approval",
    "error_handling": "fail_chain",
    "input": {
      "expense_id": "exp_42",
      "amount": 450.00,
      "submitter": "alice@example.com"
    },
    "steps": [
      {
        "name": "Validate Expense",
        "type": "http_call",
        "url": "https://api.example.com/expenses/validate",
        "method": "POST",
        "body": {
          "expense_id": "{{input.expense_id}}",
          "amount": "{{input.amount}}"
        },
        "max_attempts": 3
      },
      {
        "name": "Manager Approval",
        "type": "gated",
        "gate": {
          "message": "Approve expense of ${{input.amount}} submitted by {{input.submitter}}?",
          "recipients": ["manager@example.com"],
          "timeout": "48h",
          "on_timeout": "cancel"
        }
      },
      {
        "name": "Cooling Period",
        "type": "delay",
        "delay": "1h"
      },
      {
        "name": "Process Payment",
        "type": "http_call",
        "url": "https://api.example.com/payments",
        "method": "POST",
        "body": {
          "expense_id": "{{input.expense_id}}",
          "validation_ref": "{{steps.0.response.reference}}"
        },
        "condition": "{{steps.1.status}} == confirmed"
      }
    ]
  }'
```

</TabItem>
</Tabs>

---

## Variable Interpolation

Chains support `{{...}}` expressions in step URLs, headers, bodies, and gate messages. Variables are resolved at the moment each step executes, so later steps can reference results from earlier ones.

### Input variables

Access values from the chain's `input` object:

```
{{input.expense_id}}     -> "exp_42"
{{input.amount}}         -> 450.00
{{input.submitter}}      -> "alice@example.com"
```

### Previous step responses

Access the HTTP response body from a completed webhook step. Steps are zero-indexed:

```
{{steps.0.response.reference}}   -> value from step 0's JSON response
{{steps.0.response.data.id}}     -> nested field access
{{steps.2.response.total}}       -> value from step 2's response
```

### Step status

Check what happened in a previous step:

```
{{steps.0.status}}   -> "executed", "failed", "skipped"
{{steps.1.status}}   -> "confirmed", "declined" (for approval steps)
```

**Status values by step type:**

| Step Type | Possible Statuses |
|-----------|------------------|
| HTTP (webhook) | `executed`, `failed`, `skipped` |
| Approval | `confirmed`, `declined`, `skipped` |
| Wait | `executed`, `skipped` |

---

## Conditions

Add a `condition` to any step to make it execute only when the expression evaluates to true. If the condition is not met, the step is marked as `skipped` and the chain continues.

### Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `==` | Equal | `{{steps.1.status}} == confirmed` |
| `!=` | Not equal | `{{steps.0.status}} != failed` |
| `contains` | String contains | `{{steps.0.response.role}} contains admin` |
| `not_contains` | String does not contain | `{{steps.0.response.tags}} not_contains deprecated` |
| `starts_with` | Starts with | `{{steps.0.response.env}} starts_with prod` |
| `ends_with` | Ends with | `{{steps.0.response.email}} ends_with @example.com` |
| `>` | Greater than | `{{steps.0.response.total}} > 1000` |
| `<` | Less than | `{{steps.0.response.priority}} < 3` |
| `>=` | Greater than or equal | `{{steps.0.response.score}} >= 80` |
| `<=` | Less than or equal | `{{steps.0.response.risk}} <= 5` |

### Examples

```
// Only process payment if manager approved
{{steps.1.status}} == confirmed

// Skip notification for low-value orders
{{steps.0.response.total}} > 100

// Execute only if the validation response contains "approved"
{{steps.0.response.result}} == approved
```

---

## Error Handling

Set the `error_handling` field on the chain to control what happens when a step fails.

### `fail_chain` (default)

The entire chain stops immediately. No further steps execute. The chain status becomes `failed`.

```json
{ "error_handling": "fail_chain" }
```

Use this for workflows where every step is critical -- if payment validation fails, you do not want to proceed to approval.

### `skip_step`

The failed step is marked as `skipped` and the chain continues to the next step. Use this for workflows with optional steps.

```json
{ "error_handling": "skip_step" }
```

Use this when some steps are nice-to-have. For example, a notification step that fails should not block a payment.

:::tip
Combine `skip_step` with conditions for fine-grained control. Even with `skip_step` enabled, you can use a condition on a later step to check whether a critical earlier step succeeded:

```
"condition": "{{steps.0.status}} == executed"
```
:::

---

## Chain Statuses

| Status | Description |
|--------|-------------|
| `pending` | Chain created, first step has not started |
| `running` | At least one step has started executing |
| `completed` | All steps finished (executed, confirmed, or skipped) |
| `failed` | A step failed and `error_handling` is `fail_chain` |
| `cancelled` | Chain was cancelled via the API |

---

## Managing Chains

<Tabs>
<TabItem value="nodejs" label="Node.js">

```ts
// Get chain details
const chain = await client.getChain('chn_abc123');
console.log(chain.status, chain.current_step);

// List chains
const chains = await client.listChains({ status: 'running' });

// Cancel a chain (cancels all pending steps)
await client.cancelChain('chn_abc123');
```

</TabItem>
<TabItem value="laravel" label="Laravel">

```php
// Get chain details
$chain = CallMeLater::getChain('chn_abc123');

// List chains
$chains = CallMeLater::listChains(['status' => 'running']);

// Cancel a chain
CallMeLater::cancelChain('chn_abc123');
```

</TabItem>
<TabItem value="curl" label="curl">

```bash
# Get chain
curl https://callmelater.io/api/v1/chains/chn_abc123 \
  -H "Authorization: Bearer sk_live_..."

# List running chains
curl "https://callmelater.io/api/v1/chains?status=running" \
  -H "Authorization: Bearer sk_live_..."

# Cancel chain
curl -X DELETE https://callmelater.io/api/v1/chains/chn_abc123 \
  -H "Authorization: Bearer sk_live_..."
```

</TabItem>
</Tabs>

:::note
Cancelling a chain cancels all pending steps. Steps that have already executed are not rolled back.
:::
