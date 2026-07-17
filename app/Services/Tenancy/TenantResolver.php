<?php

namespace App\Services\Tenancy;

use App\Models\TenantDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TenantResolver
{
    public function resolveFromRequest(Request $request): ?TenantDomain
    {
        $host = $this->normalizeHost($request->getHost());
        $storeSlug = trim((string) $request->query('store_slug', ''), " \t\n\r\0\x0B.");

        if ($host === '' || in_array($host, $this->reservedHosts(), true)) {
            return $storeSlug !== '' ? $this->resolveFromStoreSlug($storeSlug) : null;
        }

        $resolver = fn () => TenantDomain::query()
            ->with('tenant')
            ->where('hostname', $host)
            ->where(function ($query) {
                $query
                    ->where('domain_type', 'subdomain')
                    ->orWhere(function ($customDomainQuery): void {
                        $customDomainQuery
                            ->where('domain_type', 'custom')
                            ->where('verification_status', 'verified');
                    });
            })
            ->first();

        if (config('tenancy.cache.enabled', true)) {
            $ttl = max(1, (int) config('tenancy.cache.ttl', 300));

            return Cache::remember("tenant-domain:{$host}", now()->addSeconds($ttl), $resolver);
        }

        return $resolver();
    }

    public function resolveFromStoreSlug(string $storeSlug): ?TenantDomain
    {
        $normalizedSlug = strtolower(trim($storeSlug));

        if ($normalizedSlug === '') {
            return null;
        }

        $resolver = fn () => TenantDomain::query()
            ->with('tenant')
            ->whereHas('tenant', fn ($query) => $query->where('slug', $normalizedSlug))
            ->where(function ($query) {
                $query
                    ->where('domain_type', 'subdomain')
                    ->orWhere(function ($customDomainQuery): void {
                        $customDomainQuery
                            ->where('domain_type', 'custom')
                            ->where('verification_status', 'verified');
                    });
            })
            ->orderByDesc('is_primary')
            ->orderByDesc('is_fallback')
            ->first();

        if (config('tenancy.cache.enabled', true)) {
            $ttl = max(1, (int) config('tenancy.cache.ttl', 300));

            return Cache::remember("tenant-domain:slug:{$normalizedSlug}", now()->addSeconds($ttl), $resolver);
        }

        return $resolver();
    }

    public function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? '';

        return $host;
    }

    /**
     * @return array<int, string>
     */
    private function reservedHosts(): array
    {
        return array_values(array_filter([
            $this->normalizeHost((string) config('saas.marketing_host', '')),
            $this->normalizeHost((string) config('saas.owner_host', '')),
            $this->normalizeHost((string) config('saas.merchant_host', '')),
            ...array_map(fn (mixed $host): string => $this->normalizeHost((string) $host), (array) config('saas.owner_host_aliases', [])),
        ]));
    }
}
