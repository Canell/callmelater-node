/**
 * Code examples in multiple languages for the documentation and homepage.
 * Uses the new friendlier terminology: approval, schedule, wait
 */

// Simple webhook example - shows the core value quickly
export const createHttpAction = {
  curl: `curl -X POST https://api.callmelater.io/v1/actions \\
  -H "Authorization: Bearer sk_live_..." \\
  -H "Content-Type: application/json" \\
  -d '{
    "name": "Trial expiration",
    "schedule": { "wait": "14d" },
    "request": {
      "url": "https://your-app.com/webhooks/trial-expired",
      "method": "POST",
      "body": { "user_id": 42 }
    }
  }'`,

  php: `<?php
$response = Http::withToken('sk_live_...')
    ->post('https://api.callmelater.io/v1/actions', [
        'name' => 'Trial expiration',
        'schedule' => ['wait' => '14d'],
        'request' => [
            'url' => 'https://your-app.com/webhooks/trial-expired',
            'method' => 'POST',
            'body' => ['user_id' => 42],
        ],
    ]);`,

  python: `import requests

response = requests.post(
    "https://api.callmelater.io/v1/actions",
    headers={"Authorization": "Bearer sk_live_..."},
    json={
        "name": "Trial expiration",
        "schedule": {"wait": "14d"},
        "request": {
            "url": "https://your-app.com/webhooks/trial-expired",
            "body": {"user_id": 42},
        },
    },
)`,

  javascript: `const action = await fetch('https://api.callmelater.io/v1/actions', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer sk_live_...',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'Trial expiration',
    schedule: { wait: '14d' },
    request: {
      url: 'https://your-app.com/webhooks/trial-expired',
      body: { user_id: 42 },
    },
  }),
});`,

  node: `const axios = require('axios');

const { data } = await axios.post(
  'https://api.callmelater.io/v1/actions',
  {
    name: 'Trial expiration',
    schedule: { wait: '14d' },
    request: {
      url: 'https://your-app.com/webhooks/trial-expired',
      body: { user_id: 42 },
    },
  },
  { headers: { Authorization: 'Bearer sk_live_...' } }
);`,

  go: `payload := map[string]interface{}{
    "name": "Trial expiration",
    "schedule": map[string]string{"wait": "14d"},
    "request": map[string]interface{}{
        "url":  "https://your-app.com/webhooks/trial-expired",
        "body": map[string]interface{}{"user_id": 42},
    },
}

body, _ := json.Marshal(payload)
req, _ := http.NewRequest("POST", "https://api.callmelater.io/v1/actions", bytes.NewBuffer(body))
req.Header.Set("Authorization", "Bearer sk_live_...")
req.Header.Set("Content-Type", "application/json")`,

  ruby: `response = HTTParty.post(
  'https://api.callmelater.io/v1/actions',
  headers: { 'Authorization' => 'Bearer sk_live_...' },
  body: {
    name: 'Trial expiration',
    schedule: { wait: '14d' },
    request: {
      url: 'https://your-app.com/webhooks/trial-expired',
      body: { user_id: 42 }
    }
  }.to_json
)`,

  java: `HttpClient client = HttpClient.newHttpClient();

String json = """
  {
    "name": "Trial expiration",
    "schedule": { "wait": "14d" },
    "request": {
      "url": "https://your-app.com/webhooks/trial-expired",
      "body": { "user_id": 42 }
    }
  }
  """;

HttpRequest request = HttpRequest.newBuilder()
    .uri(URI.create("https://api.callmelater.io/v1/actions"))
    .header("Authorization", "Bearer sk_live_...")
    .header("Content-Type", "application/json")
    .POST(HttpRequest.BodyPublishers.ofString(json))
    .build();`,
};

// Human approval example - key differentiator!
export const createApprovalAction = {
  curl: `curl -X POST https://api.callmelater.io/v1/actions \\
  -H "Authorization: Bearer sk_live_..." \\
  -H "Content-Type: application/json" \\
  -d '{
    "name": "Deploy to production",
    "mode": "approval",
    "schedule": { "wait": "5m" },
    "gate": {
      "message": "Ready to deploy v2.1 to production?",
      "recipients": ["ops@example.com"],
      "channels": ["email"]
    }
  }'`,

  php: `<?php
$response = Http::withToken('sk_live_...')
    ->post('https://api.callmelater.io/v1/actions', [
        'name' => 'Deploy to production',
        'mode' => 'approval',
        'schedule' => ['wait' => '5m'],
        'gate' => [
            'message' => 'Ready to deploy v2.1 to production?',
            'recipients' => ['ops@example.com'],
            'channels' => ['email'],
        ],
    ]);`,

  python: `response = requests.post(
    "https://api.callmelater.io/v1/actions",
    headers={"Authorization": "Bearer sk_live_..."},
    json={
        "name": "Deploy to production",
        "mode": "approval",
        "schedule": {"wait": "5m"},
        "gate": {
            "message": "Ready to deploy v2.1 to production?",
            "recipients": ["ops@example.com"],
            "channels": ["email"],
        },
    },
)`,

  javascript: `const action = await fetch('https://api.callmelater.io/v1/actions', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer sk_live_...',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'Deploy to production',
    mode: 'approval',
    schedule: { wait: '5m' },
    gate: {
      message: 'Ready to deploy v2.1 to production?',
      recipients: ['ops@example.com'],
      channels: ['email'],
    },
  }),
});`,

  node: `const { data } = await axios.post(
  'https://api.callmelater.io/v1/actions',
  {
    name: 'Deploy to production',
    mode: 'approval',
    schedule: { wait: '5m' },
    gate: {
      message: 'Ready to deploy v2.1 to production?',
      recipients: ['ops@example.com'],
      channels: ['email'],
    },
  },
  { headers: { Authorization: 'Bearer sk_live_...' } }
);`,
};

// Teams/Slack example - showcase integration
export const createTeamsSlackAction = {
  curl: `curl -X POST https://api.callmelater.io/v1/actions \\
  -H "Authorization: Bearer sk_live_..." \\
  -H "Content-Type: application/json" \\
  -d '{
    "name": "Infrastructure approval",
    "mode": "approval",
    "schedule": { "wait": "1h" },
    "gate": {
      "message": "Approve scaling to 10 instances?",
      "recipients": ["channel:teams-ops-123"],
      "channels": ["teams"]
    }
  }'`,

  javascript: `const action = await fetch('https://api.callmelater.io/v1/actions', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer sk_live_...',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'Infrastructure approval',
    mode: 'approval',
    schedule: { wait: '1h' },
    gate: {
      message: 'Approve scaling to 10 instances?',
      recipients: ['channel:slack-devops-456'],  // or channel:teams-ops-123
      channels: ['slack'],
    },
  }),
});`,
};

// JSON examples for inline display
export const httpActionJson = `{
  "name": "Expire trial",
  "schedule": { "wait": "14d" },
  "request": {
    "url": "https://api.example.com/trials/expire",
    "body": { "user_id": 42 }
  },
  "max_attempts": 5
}`;

export const approvalActionJson = `{
  "mode": "approval",
  "name": "Deploy to production",
  "schedule": { "wait": "30m" },
  "gate": {
    "message": "Deploy v2.1 to production?",
    "recipients": ["ops@example.com", "channel:slack-deploys"],
    "channels": ["email", "slack"],
    "max_snoozes": 3
  }
}`;

// Workflow example
export const workflowJson = `{
  "name": "User onboarding",
  "steps": [
    {
      "name": "Create account",
      "type": "webhook",
      "url": "https://api.example.com/accounts"
    },
    {
      "name": "Manager approval",
      "type": "approval",
      "gate": {
        "message": "Approve new user {{email}}?",
        "recipients": ["channel:slack-approvals"]
      }
    },
    {
      "name": "Wait for setup",
      "type": "wait",
      "delay": "1h"
    },
    {
      "name": "Send welcome email",
      "type": "webhook",
      "url": "https://api.example.com/welcome"
    }
  ]
}`;

// Legacy exports for backwards compatibility
export const createReminderAction = createApprovalAction;
export const reminderActionJson = approvalActionJson;
