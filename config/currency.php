<?php

return [
    'base_code' => env('CURRENCY_BASE_CODE', env('CURRENCY', 'USD')),
    'quote_ttl_minutes' => (int) env('CURRENCY_QUOTE_TTL_MINUTES', 20),

    'sync' => [
        'enabled' => (bool) env('CURRENCY_AUTO_SYNC_ENABLED', true),
        'driver' => env('CURRENCY_RATE_DRIVER', 'open_er_api'),
        'endpoint' => env('CURRENCY_RATE_ENDPOINT', 'https://open.er-api.com/v6/latest/{base}'),
        'api_key' => env('CURRENCY_RATE_API_KEY'),
        'timeout' => (int) env('CURRENCY_RATE_TIMEOUT', 10),
    ],

    'fallback_rates' => [
        'USD' => 1,
        'BDT' => 120,
        'INR' => 83,
        'AUD' => 1.5,
        'EUR' => 0.92,
        'GBP' => 0.79,
        'CAD' => 1.36,
        'JPY' => 157,
        'NGN' => 1500,
        'PKR' => 278,
    ],

    'zero_decimal' => [
        'BIF',
        'CLP',
        'DJF',
        'GNF',
        'ISK',
        'JPY',
        'KMF',
        'KRW',
        'MGA',
        'PYG',
        'RWF',
        'UGX',
        'VND',
        'VUV',
        'XAF',
        'XOF',
        'XPF',
    ],
];
