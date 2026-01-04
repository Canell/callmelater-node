<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Twilio Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Twilio credentials for sending SMS messages.
    | Get these from https://console.twilio.com
    |
    */

    'sid' => env('TWILIO_SID'),

    'auth_token' => env('TWILIO_AUTH_TOKEN'),

    'from_number' => env('TWILIO_FROM_NUMBER'),

    /*
    |--------------------------------------------------------------------------
    | SMS Settings
    |--------------------------------------------------------------------------
    */

    'enabled' => env('TWILIO_ENABLED', false),
];
