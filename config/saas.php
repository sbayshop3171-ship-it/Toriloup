<?php

return [
    'root_domain'                => env('SAAS_ROOT_DOMAIN', 'toriloup.com'),
    'marketing_host'             => env('SAAS_MARKETING_HOST', 'toriloup.com'),
    'owner_host'                 => env('SAAS_OWNER_HOST', 'owner.toriloup.com'),
    'owner_host_aliases'         => array_values(array_filter(array_map('trim', explode(',', env('SAAS_OWNER_HOST_ALIASES', 'admin.toriloup.com'))))),
    'merchant_host'              => env('SAAS_MERCHANT_HOST', 'merchant.toriloup.com'),
    'fallback_subdomain_suffix'  => env('SAAS_STOREFRONT_SUFFIX', 'toriloup.com'),
    'wallet'                     => [
        'holding_days'           => (int) env('SAAS_WALLET_HOLDING_DAYS', 0),
        'min_withdrawal_amount'  => (float) env('SAAS_WALLET_MIN_WITHDRAWAL_AMOUNT', 0),
    ],
    'reserved_store_slugs'       => array_values(array_filter(array_map('trim', explode(',', env(
        'SAAS_RESERVED_STORE_SLUGS',
        'admin,administrator,api,app,assets,billing,blog,cdn,cpanel,dev,ftp,help,mail,merchant,owner,panel,platform,root,ssl,status,store,storefront,support,system,webdisk,webmail,wildcard,www'
    ))))),
    'require_owner_2fa'          => filter_var(env('SAAS_REQUIRE_OWNER_2FA', true), FILTER_VALIDATE_BOOL),
];
