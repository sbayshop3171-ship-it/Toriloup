<?php

return [
    'api_base_url' => env('CLOUDFLARE_API_BASE_URL', 'https://api.cloudflare.com/client/v4'),
    'api_token' => env('CLOUDFLARE_API_TOKEN'),
    'saas_zone_id' => env('CLOUDFLARE_SAAS_ZONE_ID'),
    'timeout' => (int) env('CLOUDFLARE_TIMEOUT', 15),
    'proxy_custom_domains' => filter_var(env('CLOUDFLARE_PROXY_CUSTOM_DOMAINS', false), FILTER_VALIDATE_BOOL),
];
