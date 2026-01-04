<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Call Security
    |--------------------------------------------------------------------------
    |
    | Security settings for outgoing HTTP calls to prevent SSRF attacks
    | and abuse of the service.
    |
    */

    'http' => [
        // Block requests to private/internal IP ranges
        'block_private_ips' => env('CML_BLOCK_PRIVATE_IPS', true),

        // Blocked IP ranges (CIDR notation)
        'blocked_ranges' => [
            '10.0.0.0/8',        // Private Class A
            '172.16.0.0/12',     // Private Class B
            '192.168.0.0/16',    // Private Class C
            '127.0.0.0/8',       // Loopback
            '169.254.0.0/16',    // Link-local
            '0.0.0.0/8',         // Current network
            '100.64.0.0/10',     // Carrier-grade NAT
            '192.0.0.0/24',      // IETF Protocol Assignments
            '192.0.2.0/24',      // Documentation (TEST-NET-1)
            '198.51.100.0/24',   // Documentation (TEST-NET-2)
            '203.0.113.0/24',    // Documentation (TEST-NET-3)
            '224.0.0.0/4',       // Multicast
            '240.0.0.0/4',       // Reserved
            '255.255.255.255/32', // Broadcast
            '::1/128',           // IPv6 loopback
            'fc00::/7',          // IPv6 unique local
            'fe80::/10',         // IPv6 link-local
        ],

        // Blocked hostnames
        'blocked_hosts' => [
            'localhost',
            '*.local',
            '*.internal',
            'metadata.google.internal',      // GCP metadata
            '169.254.169.254',               // AWS/Azure/GCP metadata
        ],

        // Maximum request body size (bytes)
        'max_body_size' => env('CML_MAX_BODY_SIZE', 1048576), // 1MB

        // Maximum response size to store (bytes)
        'max_response_size' => env('CML_MAX_RESPONSE_SIZE', 10240), // 10KB

        // Request timeout (seconds)
        'timeout' => env('CML_HTTP_TIMEOUT', 30),

        // Allow redirects
        'allow_redirects' => env('CML_ALLOW_REDIRECTS', false),

        // Max redirects if allowed
        'max_redirects' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        // API requests per minute
        'api' => env('CML_RATE_LIMIT_API', 100),

        // Action creations per hour
        'create_actions' => env('CML_RATE_LIMIT_CREATE', 100),

        // Reminder responses per minute (per token)
        'responses' => env('CML_RATE_LIMIT_RESPONSES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        // Maximum actions per user
        'max_actions_per_user' => env('CML_MAX_ACTIONS_PER_USER', 1000),

        // Maximum pending actions per user
        'max_pending_actions' => env('CML_MAX_PENDING_ACTIONS', 100),

        // Maximum recipients per reminder
        'max_recipients' => env('CML_MAX_RECIPIENTS', 50),

        // Maximum schedule time in the future (days)
        'max_schedule_days' => env('CML_MAX_SCHEDULE_DAYS', 365),

        // Token expiry range (days)
        'min_token_expiry_days' => 1,
        'max_token_expiry_days' => 30,
    ],
];
