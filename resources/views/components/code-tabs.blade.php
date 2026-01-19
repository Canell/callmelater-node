@php
$codeExamples = [
    'createHttpAction' => [
        'curl' => 'curl -X POST https://api.callmelater.io/v1/actions \\
  -H "Authorization: Bearer sk_live_..." \\
  -H "Content-Type: application/json" \\
  -d \'{
    "name": "Trial expiration webhook",
    "type": "http",
    "intent": { "delay": "14d" },
    "http_request": {
      "url": "https://your-app.com/webhook",
      "method": "POST",
      "body": { "event": "trial_expired", "user_id": 42 }
    }
  }\'',
        'php' => '<?php
use GuzzleHttp\\Client;

$client = new Client();
$response = $client->post(\'https://api.callmelater.io/v1/actions\', [
    \'headers\' => [
        \'Authorization\' => \'Bearer sk_live_...\',
        \'Content-Type\' => \'application/json\',
    ],
    \'json\' => [
        \'name\' => \'Trial expiration webhook\',
        \'type\' => \'http\',
        \'intent\' => [\'delay\' => \'14d\'],
        \'http_request\' => [
            \'url\' => \'https://your-app.com/webhook\',
            \'method\' => \'POST\',
            \'body\' => [
                \'event\' => \'trial_expired\',
                \'user_id\' => 42,
            ],
        ],
    ],
]);

$action = json_decode($response->getBody(), true);',
        'python' => 'import requests

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

action = response.json()',
        'javascript' => 'const response = await fetch(\'https://api.callmelater.io/v1/actions\', {
  method: \'POST\',
  headers: {
    \'Authorization\': \'Bearer sk_live_...\',
    \'Content-Type\': \'application/json\',
  },
  body: JSON.stringify({
    name: \'Trial expiration webhook\',
    type: \'http\',
    intent: { delay: \'14d\' },
    http_request: {
      url: \'https://your-app.com/webhook\',
      method: \'POST\',
      body: { event: \'trial_expired\', user_id: 42 },
    },
  }),
});

const action = await response.json();',
        'node' => 'const axios = require(\'axios\');

const response = await axios.post(
  \'https://api.callmelater.io/v1/actions\',
  {
    name: \'Trial expiration webhook\',
    type: \'http\',
    intent: { delay: \'14d\' },
    http_request: {
      url: \'https://your-app.com/webhook\',
      method: \'POST\',
      body: { event: \'trial_expired\', user_id: 42 },
    },
  },
  {
    headers: {
      Authorization: \'Bearer sk_live_...\',
      \'Content-Type\': \'application/json\',
    },
  }
);

const action = response.data;',
        'java' => 'import java.net.http.*;
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
);',
        'go' => 'package main

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
}',
        'ruby' => 'require \'net/http\'
require \'json\'

uri = URI(\'https://api.callmelater.io/v1/actions\')
http = Net::HTTP.new(uri.host, uri.port)
http.use_ssl = true

request = Net::HTTP::Post.new(uri)
request[\'Authorization\'] = \'Bearer sk_live_...\'
request[\'Content-Type\'] = \'application/json\'
request.body = {
  name: \'Trial expiration webhook\',
  type: \'http\',
  intent: { delay: \'14d\' },
  http_request: {
    url: \'https://your-app.com/webhook\',
    method: \'POST\',
    body: { event: \'trial_expired\', user_id: 42 }
  }
}.to_json

response = http.request(request)
action = JSON.parse(response.body)',
    ],
];

$languageConfig = [
    'curl' => ['id' => 'curl', 'label' => 'cURL'],
    'php' => ['id' => 'php', 'label' => 'PHP'],
    'python' => ['id' => 'python', 'label' => 'Python'],
    'javascript' => ['id' => 'javascript', 'label' => 'JavaScript'],
    'node' => ['id' => 'node', 'label' => 'Node.js'],
    'java' => ['id' => 'java', 'label' => 'Java'],
    'go' => ['id' => 'go', 'label' => 'Go'],
    'ruby' => ['id' => 'ruby', 'label' => 'Ruby'],
];

$currentExamples = $codeExamples[$examples] ?? $codeExamples['createHttpAction'];
$defaultTab = $defaultTab ?? 'curl';
@endphp

<div class="code-tabs">
    <div class="code-tabs-header">
        <div class="code-tabs-nav">
            @foreach($languageConfig as $lang => $config)
                @if(isset($currentExamples[$lang]))
                    <button class="code-tab @if($lang === $defaultTab) active @endif" data-lang="{{ $lang }}">
                        {{ $config['label'] }}
                    </button>
                @endif
            @endforeach
        </div>
        <button class="copy-btn" title="Copy code">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
        </button>
    </div>
    <div class="code-tabs-content">
        @foreach($languageConfig as $lang => $config)
            @if(isset($currentExamples[$lang]))
                <div class="code-panel @if($lang === $defaultTab) active @endif" data-lang="{{ $lang }}">
                    <pre><code>{{ $currentExamples[$lang] }}</code></pre>
                </div>
            @endif
        @endforeach
    </div>
    <div class="scroll-indicator">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
        <span>Scroll for more</span>
    </div>
</div>
