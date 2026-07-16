<?php

return [
    'tenant_request_attribute' => 'saas.tenant',
    'tenant_domain_attribute'  => 'saas.tenant_domain',
    'surface_request_attribute'=> 'saas.surface',
    'active_tenant_statuses'   => ['active'],
    'cache'                    => [
        'enabled' => filter_var(env('TENANCY_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'ttl'     => (int) env('TENANCY_CACHE_TTL', 300),
    ],
];
