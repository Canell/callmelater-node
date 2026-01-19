<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Brevo Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Brevo credentials for sending SMS messages.
    | Get your API key from https://app.brevo.com/settings/keys/api
    |
    */

    'api_key' => env('BREVO_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | SMS Settings
    |--------------------------------------------------------------------------
    */

    'enabled' => env('BREVO_SMS_ENABLED', false),

    // Sender name (max 11 alphanumeric chars or 15 numeric chars)
    'sender' => env('BREVO_SMS_SENDER', 'CallMeLater'),

    // SMS type: 'transactional' or 'marketing'
    'type' => 'transactional',
];
