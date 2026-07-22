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

        $resolver = fn () => $this->resolveHost($host);

        if (config('tenancy.cache.enabled', true)) {
            $ttl = max(1, (int) config('tenancy.cache.ttl', 300));

            return Cache::remember("tenant-domain:{$host}", now()->addSeconds($ttl), $resolver);
        }

        return $resolver();
    }

    private function resolveHost(string $host): ?TenantDomain
    {
        $domain = $this->resolveExactHost($host);

        if ($domain instanceof TenantDomain || !str_starts_with($host, 'www.')) {
            return $domain;
        }

        return TenantDomain::query()
            ->with('tenant')
            ->where('hostname', substr($host, 4))
            ->where('domain_type', 'custom')
            ->where('verification_status', 'verified')
            ->first();
    }

    private function resolveExactHost(string $host): ?TenantDomain
    {
        $domain = TenantDomain::query()
            ->with(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])
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

        if ($domain instanceof TenantDomain && $this->fallbackIsSuppressed($domain)) {
            return null;
        }

        return $domain;
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

    public function primaryCustomDomainForFallbackHost(string $host): ?TenantDomain
    {
        $fallbackDomain = TenantDomain::query()
            ->with(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])
            ->where('hostname', $this->normalizeHost($host))
            ->where('domain_type', 'subdomain')
            ->where('is_fallback', true)
            ->where('verification_status', 'verified')
            ->first();

        if (!$fallbackDomain instanceof TenantDomain) {
            return null;
        }

        return $this->activePrimaryCustomDomain($fallbackDomain);
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

    private function fallbackIsSuppressed(TenantDomain $domain): bool
    {
        return $domain->domain_type === 'subdomain'
            && $domain->is_fallback
            && $this->activePrimaryCustomDomain($domain) instanceof TenantDomain;
    }

    private function activePrimaryCustomDomain(TenantDomain $domain): ?TenantDomain
    {
        $domain->loadMissing(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')]);

        return $domain->tenant?->domains->first(function (TenantDomain $candidate): bool {
            return $candidate->domain_type === 'custom'
                && $candidate->is_primary
                && $candidate->verification_status === 'verified';
        });
    }
}
