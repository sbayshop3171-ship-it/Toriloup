<?php

return [
    'root_domain'                => env('SAAS_ROOT_DOMAIN', 'toriloup.com'),
    'marketing_host'             => env('SAAS_MARKETING_HOST', 'toriloup.com'),
    'owner_host'                 => env('SAAS_OWNER_HOST', 'owner.toriloup.com'),
    'merchant_host'              => env('SAAS_MERCHANT_HOST', 'merchant.toriloup.com'),
    'fallback_subdomain_suffix'  => env('SAAS_STOREFRONT_SUFFIX', 'toriloup.com'),
    'require_owner_2fa'          => filter_var(env('SAAS_REQUIRE_OWNER_2FA', true), FILTER_VALIDATE_BOOL),
];
