<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'expo_push' => [
        'enabled' => env('EXPO_PUSH_ENABLED', true),
        'timeout' => env('EXPO_PUSH_TIMEOUT', 10),
        'access_token' => env('EXPO_PUSH_ACCESS_TOKEN', ''),
    ],

    /*
    | ExtraCash mobile-money integration (third-party GeePay gateway API at gateway.mygeepay.com).
    | Prefer EXTRACASH_GATEWAY_* env vars. GEEPAY_* names are still read as fallbacks.
    | Default webhook header name matches GeePay until the vendor supplies a new one.
    */
    'extracash_gateway' => [
        'base_url' => env('EXTRACASH_GATEWAY_BASE_URL', env('GEEPAY_BASE_URL', 'https://gateway.mygeepay.com/api/v1')),
        'client_id' => env('EXTRACASH_GATEWAY_CLIENT_ID', env('GEEPAY_CLIENT_ID')),
        'auth_signature' => env('EXTRACASH_GATEWAY_AUTH_SIGNATURE', env('GEEPAY_AUTH_SIGNATURE')),
        'bearer_token' => env('EXTRACASH_GATEWAY_BEARER_TOKEN', env('GEEPAY_BEARER_TOKEN')),
        'callback_url' => env('EXTRACASH_GATEWAY_CALLBACK_URL', env('GEEPAY_CALLBACK_URL')),
        'webhook_secret' => env('EXTRACASH_GATEWAY_WEBHOOK_SECRET', env('GEEPAY_WEBHOOK_SECRET')),
        'webhook_header' => env('EXTRACASH_GATEWAY_WEBHOOK_HEADER', env('GEEPAY_WEBHOOK_HEADER', 'X-Geepay-Webhook-Secret')),
    ],

    'smartdata' => [
        'api_key' => env('SMARTDATA_API_KEY'),
        'base_url' => env('SMARTDATA_BASE_URL', 'https://mysmartdata.tech/api/v1'),
    ],

];
