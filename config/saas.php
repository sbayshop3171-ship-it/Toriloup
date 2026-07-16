<?php

return [
    'root_domain'                => env('SAAS_ROOT_DOMAIN', 'company.com'),
    'marketing_host'             => env('SAAS_MARKETING_HOST', 'company.com'),
    'owner_host'                 => env('SAAS_OWNER_HOST', 'owner.company.com'),
    'merchant_host'              => env('SAAS_MERCHANT_HOST', 'merchant.company.com'),
    'fallback_subdomain_suffix'  => env('SAAS_STOREFRONT_SUFFIX', 'company.com'),
    'require_owner_2fa'          => filter_var(env('SAAS_REQUIRE_OWNER_2FA', true), FILTER_VALIDATE_BOOL),
];
