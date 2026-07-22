<?php

return [
    'api_base_url' => env('CLOUDFLARE_API_BASE_URL', 'https://api.cloudflare.com/client/v4'),
    'api_token' => env('CLOUDFLARE_API_TOKEN'),
    'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
    'saas_zone_id' => env('CLOUDFLARE_SAAS_ZONE_ID'),
    'timeout' => (int) env('CLOUDFLARE_TIMEOUT', 15),
    'proxy_custom_domains' => filter_var(env('CLOUDFLARE_PROXY_CUSTOM_DOMAINS', false), FILTER_VALIDATE_BOOL),
    'full_zone' => [
        'origin_ipv4' => env('CLOUDFLARE_FULL_ZONE_ORIGIN_IPV4'),
        'origin_ipv6' => env('CLOUDFLARE_FULL_ZONE_ORIGIN_IPV6'),
        'proxy_records' => filter_var(env('CLOUDFLARE_FULL_ZONE_PROXY_RECORDS', true), FILTER_VALIDATE_BOOL),
        'ttl' => (int) env('CLOUDFLARE_FULL_ZONE_TTL', 1),
    ],
];
