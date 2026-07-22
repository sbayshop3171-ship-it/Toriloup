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

    'mapbox' => [
        'access_token' => env('MAPBOX_ACCESS_TOKEN', env('VITE_MAPBOX_ACCESS_TOKEN')),
    ],

    'fastpanel' => [
        'base_url' => env('FASTPANEL_BASE_URL'),
        'username' => env('FASTPANEL_USERNAME'),
        'password' => env('FASTPANEL_PASSWORD'),
        'storefront_site_id' => env('FASTPANEL_STOREFRONT_SITE_ID'),
        'timeout' => (int) env('FASTPANEL_TIMEOUT', 15),
        'verify_tls' => filter_var(env('FASTPANEL_VERIFY_TLS', true), FILTER_VALIDATE_BOOL),
        'include_www_alias' => filter_var(env('FASTPANEL_STOREFRONT_INCLUDE_WWW_ALIAS', true), FILTER_VALIDATE_BOOL),
        'takeover_alias_conflicts' => filter_var(env('FASTPANEL_STOREFRONT_TAKEOVER_ALIASES', true), FILTER_VALIDATE_BOOL),
    ],

];
