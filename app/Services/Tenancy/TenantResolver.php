<?php

namespace App\Services\Tenancy;

use App\Models\TenantDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TenantResolver
{
    public function resolveFromRequest(Request $request): ?TenantDomain
    {
        $host = $this->normalizeHost($request->getHost());
        $storeSlug = trim((string) $request->query('store_slug', ''), " \t\n\r\0\x0B.");

        if ($host === '' || $this->isReservedHost($host)) {
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

    public function isReservedHost(string $host): bool
    {
        return in_array($this->normalizeHost($host), $this->reservedHosts(), true);
    }

    public function isReservedStorefrontHost(string $host): bool
    {
        $host = $this->normalizeHost($host);

        return $host !== ''
            && !in_array($host, $this->configuredSurfaceHosts(), true)
            && in_array($host, $this->reservedHosts(), true);
    }

    /**
     * @return array<int, string>
     */
    public function reservedHosts(): array
    {
        return array_values(array_filter(array_unique([
            ...$this->configuredSurfaceHosts(),
            ...$this->reservedStorefrontHosts(),
        ])));
    }

    /**
     * @return array<int, string>
     */
    private function configuredSurfaceHosts(): array
    {
        return array_values(array_filter(array_unique([
            $this->normalizeHost((string) config('saas.marketing_host', '')),
            $this->normalizeHost((string) config('saas.owner_host', '')),
            $this->normalizeHost((string) config('saas.merchant_host', '')),
            ...array_map(fn (mixed $host): string => $this->normalizeHost((string) $host), (array) config('saas.owner_host_aliases', [])),
        ])));
    }

    /**
     * @return array<int, string>
     */
    private function reservedStorefrontHosts(): array
    {
        $suffixes = array_values(array_filter(array_unique([
            $this->normalizeHost((string) config('saas.fallback_subdomain_suffix', '')),
            $this->normalizeHost((string) config('saas.root_domain', '')),
        ])));

        $reservedSlugs = array_values(array_filter(array_map(
            static fn (mixed $slug): string => Str::slug((string) $slug),
            (array) config('saas.reserved_store_slugs', [])
        )));

        $hosts = [];

        foreach ($reservedSlugs as $slug) {
            foreach ($suffixes as $suffix) {
                $hosts[] = "{$slug}.{$suffix}";
            }
        }

        return array_values(array_unique($hosts));
    }
}
