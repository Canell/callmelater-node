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
        // IMPORTANT: When enabled, hostnames are resolved via DNS and ALL
        // returned IPs are checked against blocked_ranges. This prevents
        // SSRF attacks where evil.example.com resolves to 10.0.0.5
        'block_private_ips' => env('CML_BLOCK_PRIVATE_IPS', true),

        // Blocked IP ranges (CIDR notation)
        // These are checked against resolved IPs, not just the hostname
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
            '*.onion',
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
    | Plan-Based Limits
    |--------------------------------------------------------------------------
    |
    | Limits are enforced per plan. Users on the free plan get restricted
    | access, while paid plans unlock more capacity.
    |
    */

    'plans' => [
        'free' => [
            'max_actions_per_month' => 100,
            'max_pending_actions' => 10,
            'max_schedule_days' => 30,
            'max_recipients' => 3,
            'max_retries' => 3,
            'new_recipients_per_day' => 5,
            'optin_emails_per_day' => 10,
            'history_days' => 7,
        ],
        'pro' => [
            'max_actions_per_month' => 5000,
            'max_pending_actions' => 100,
            'max_schedule_days' => 365,
            'max_recipients' => 20,
            'max_retries' => 5,
            'new_recipients_per_day' => 50,
            'optin_emails_per_day' => 100,
            'history_days' => 30,
        ],
        'business' => [
            'max_actions_per_month' => 50000,
            'max_pending_actions' => 1000,
            'max_schedule_days' => 730, // 2 years
            'max_recipients' => 50,
            'max_retries' => 10,
            'new_recipients_per_day' => 200,
            'optin_emails_per_day' => 500,
            'history_days' => 365,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Limits (apply to all plans)
    |--------------------------------------------------------------------------
    */

    'limits' => [
        // Token expiry range (days)
        'min_token_expiry_days' => 1,
        'max_token_expiry_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Monitor (Self-Monitoring / Dogfooding)
    |--------------------------------------------------------------------------
    |
    | Automated health monitoring that uses CallMeLater to monitor itself.
    | Creates internal reminder actions when components are degraded
    | and auto-creates incidents for critical issues.
    |
    */

    'health_monitor' => [
        // Enable/disable automated health monitoring
        'enabled' => env('HEALTH_MONITOR_ENABLED', true),

        // How often to check health (minutes)
        'check_interval' => 5,

        // Minutes to wait before sending reminder (after degradation starts)
        'reminder_delay' => 15,

        // Enable/disable heartbeat (creates HTTP action to test our own pipeline)
        'heartbeat_enabled' => env('HEALTH_MONITOR_HEARTBEAT_ENABLED', true),

        // Thresholds for component status changes
        'thresholds' => [
            // Failure rate (%) - webhook delivery component
            'failure_rate_degraded' => 10,   // 10%+ = degraded
            'failure_rate_critical' => 25,   // 25%+ = outage + auto-incident

            // Stuck actions - scheduler component
            'stuck_executing_degraded' => 5,  // 5+ stuck = degraded
            'stuck_executing_critical' => 15, // 15+ stuck = outage + auto-incident

            // Queue health - scheduler component
            'queue_pending_degraded' => 100,  // 100+ pending jobs = degraded
            'queue_failed_degraded' => 10,    // 10+ failed/hour = degraded
        ],
    ],
];
