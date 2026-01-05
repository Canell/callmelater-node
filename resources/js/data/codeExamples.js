/**
 * Code examples in multiple languages for the documentation and homepage.
 */

export const createHttpAction = {
  curl: `curl -X POST https://api.callmelater.io/v1/actions \\
  -H "Authorization: Bearer sk_live_..." \\
  -H "Content-Type: application/json" \\
  -d '{
    "name": "Trial expiration webhook",
    "type": "http",
    "intent": { "delay": "14d" },
    "http_request": {
      "url": "https://your-app.com/webhook",
      "method": "POST",
      "body": { "event": "trial_expired", "user_id": 42 }
    }
  }'`,

  php: `<?php
use GuzzleHttp\\Client;

$client = new Client();
$response = $client->post('https://api.callmelater.io/v1/actions', [
    'headers' => [
        'Authorization' => 'Bearer sk_live_...',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'name' => 'Trial expiration webhook',
        'type' => 'http',
        'intent' => ['delay' => '14d'],
        'http_request' => [
            'url' => 'https://your-app.com/webhook',
            'method' => 'POST',
            'body' => [
                'event' => 'trial_expired',
                'user_id' => 42,
            ],
        ],
    ],
]);

$action = json_decode($response->getBody(), true);`,

  python: `import requests

response = requests.post(
    "https://api.callmelater.io/v1/actions",
    headers={
        "Authorization": "Bearer sk_live_...",
        "Content-Type": "application/json",
    },
    json={
        "name": "Trial expiration webhook",
        "type": "http",
        "intent": {"delay": "14d"},
        "http_request": {
            "url": "https://your-app.com/webhook",
            "method": "POST",
            "body": {"event": "trial_expired", "user_id": 42},
        },
    },
)

action = response.json()`,

  javascript: `const response = await fetch('https://api.callmelater.io/v1/actions', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer sk_live_...',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'Trial expiration webhook',
    type: 'http',
    intent: { delay: '14d' },
    http_request: {
      url: 'https://your-app.com/webhook',
      method: 'POST',
      body: { event: 'trial_expired', user_id: 42 },
    },
  }),
});

const action = await response.json();`,

  node: `const axios = require('axios');

const response = await axios.post(
  'https://api.callmelater.io/v1/actions',
  {
    name: 'Trial expiration webhook',
    type: 'http',
    intent: { delay: '14d' },
    http_request: {
      url: 'https://your-app.com/webhook',
      method: 'POST',
      body: { event: 'trial_expired', user_id: 42 },
    },
  },
  {
    headers: {
      Authorization: 'Bearer sk_live_...',
      'Content-Type': 'application/json',
    },
  }
);

const action = response.data;`,

  java: `import java.net.http.*;
import java.net.URI;

HttpClient client = HttpClient.newHttpClient();

String json = """
  {
    "name": "Trial expiration webhook",
    "type": "http",
    "intent": { "delay": "14d" },
    "http_request": {
      "url": "https://your-app.com/webhook",
      "method": "POST",
      "body": { "event": "trial_expired", "user_id": 42 }
    }
  }
  """;

HttpRequest request = HttpRequest.newBuilder()
    .uri(URI.create("https://api.callmelater.io/v1/actions"))
    .header("Authorization", "Bearer sk_live_...")
    .header("Content-Type", "application/json")
    .POST(HttpRequest.BodyPublishers.ofString(json))
    .build();

HttpResponse<String> response = client.send(
    request, HttpResponse.BodyHandlers.ofString()
);`,

  go: `package main

import (
    "bytes"
    "encoding/json"
    "net/http"
)

func createAction() {
    payload := map[string]interface{}{
        "name": "Trial expiration webhook",
        "type": "http",
        "intent": map[string]string{"delay": "14d"},
        "http_request": map[string]interface{}{
            "url":    "https://your-app.com/webhook",
            "method": "POST",
            "body":   map[string]interface{}{"event": "trial_expired", "user_id": 42},
        },
    }

    body, _ := json.Marshal(payload)
    req, _ := http.NewRequest("POST", "https://api.callmelater.io/v1/actions", bytes.NewBuffer(body))
    req.Header.Set("Authorization", "Bearer sk_live_...")
    req.Header.Set("Content-Type", "application/json")

    client := &http.Client{}
    resp, _ := client.Do(req)
    defer resp.Body.Close()
}`,

  ruby: `require 'net/http'
require 'json'

uri = URI('https://api.callmelater.io/v1/actions')
http = Net::HTTP.new(uri.host, uri.port)
http.use_ssl = true

request = Net::HTTP::Post.new(uri)
request['Authorization'] = 'Bearer sk_live_...'
request['Content-Type'] = 'application/json'
request.body = {
  name: 'Trial expiration webhook',
  type: 'http',
  intent: { delay: '14d' },
  http_request: {
    url: 'https://your-app.com/webhook',
    method: 'POST',
    body: { event: 'trial_expired', user_id: 42 }
  }
}.to_json

response = http.request(request)
action = JSON.parse(response.body)`,
};

export const createReminderAction = {
  curl: `curl -X POST https://api.callmelater.io/v1/actions \\
  -H "Authorization: Bearer sk_live_..." \\
  -H "Content-Type: application/json" \\
  -d '{
    "name": "Deployment approval",
    "type": "reminder",
    "intent": { "delay": "30m" },
    "message": "Please approve the production deployment",
    "escalation_rules": {
      "recipients": ["tech-lead@example.com"],
      "channels": ["email"]
    }
  }'`,

  php: `<?php
use GuzzleHttp\\Client;

$client = new Client();
$response = $client->post('https://api.callmelater.io/v1/actions', [
    'headers' => [
        'Authorization' => 'Bearer sk_live_...',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'name' => 'Deployment approval',
        'type' => 'reminder',
        'intent' => ['delay' => '30m'],
        'message' => 'Please approve the production deployment',
        'escalation_rules' => [
            'recipients' => ['tech-lead@example.com'],
            'channels' => ['email'],
        ],
    ],
]);`,

  python: `import requests

response = requests.post(
    "https://api.callmelater.io/v1/actions",
    headers={
        "Authorization": "Bearer sk_live_...",
        "Content-Type": "application/json",
    },
    json={
        "name": "Deployment approval",
        "type": "reminder",
        "intent": {"delay": "30m"},
        "message": "Please approve the production deployment",
        "escalation_rules": {
            "recipients": ["tech-lead@example.com"],
            "channels": ["email"],
        },
    },
)`,

  javascript: `const response = await fetch('https://api.callmelater.io/v1/actions', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer sk_live_...',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'Deployment approval',
    type: 'reminder',
    intent: { delay: '30m' },
    message: 'Please approve the production deployment',
    escalation_rules: {
      recipients: ['tech-lead@example.com'],
      channels: ['email'],
    },
  }),
});`,

  node: `const axios = require('axios');

const response = await axios.post(
  'https://api.callmelater.io/v1/actions',
  {
    name: 'Deployment approval',
    type: 'reminder',
    intent: { delay: '30m' },
    message: 'Please approve the production deployment',
    escalation_rules: {
      recipients: ['tech-lead@example.com'],
      channels: ['email'],
    },
  },
  {
    headers: {
      Authorization: 'Bearer sk_live_...',
    },
  }
);`,
};

export const httpActionJson = `{
  "type": "http",
  "name": "Expire trial subscription",
  "intent": { "delay": "14d" },
  "http_request": {
    "method": "POST",
    "url": "https://api.example.com/subscriptions/expire",
    "headers": { "X-Custom": "value" },
    "body": { "user_id": 42, "reason": "trial_ended" }
  },
  "max_attempts": 5,
  "retry_strategy": "exponential"
}`;

export const reminderActionJson = `{
  "type": "reminder",
  "name": "Approve deployment",
  "intent": { "delay": "30m" },
  "message": "Please approve the production deployment for v2.1",
  "escalation_rules": {
    "recipients": ["tech-lead@example.com", "+1234567890"],
    "channels": ["email", "sms"]
  },
  "max_snoozes": 3
}`;
