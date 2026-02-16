<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | Your CallMeLater API token. You can find this in your dashboard under
    | Settings > API Tokens. Tokens start with "sk_live_" or "sk_test_".
    |
    */

    'api_token' => env('CALLMELATER_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | The secret used to verify incoming webhook signatures. You can find this
    | in your dashboard under Settings > Security > Webhook Secret.
    |
    */

    'webhook_secret' => env('CALLMELATER_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the CallMeLater API. You typically won't need to change
    | this unless you're using a self-hosted instance.
    |
    */

    'api_url' => env('CALLMELATER_API_URL', 'https://callmelater.io'),

    /*
    |--------------------------------------------------------------------------
    | Default Timezone
    |--------------------------------------------------------------------------
    |
    | The default timezone for scheduling actions. If not set, the application
    | timezone will be used.
    |
    */

    'timezone' => env('CALLMELATER_TIMEZONE'),

    /*
    |--------------------------------------------------------------------------
    | Default Retry Policy
    |--------------------------------------------------------------------------
    |
    | Default retry settings for HTTP actions.
    |
    */

    'retry' => [
        'max_attempts' => 3,
        'backoff' => 'exponential', // 'exponential', 'linear', or 'fixed'
        'initial_delay' => 60, // seconds
    ],

];
