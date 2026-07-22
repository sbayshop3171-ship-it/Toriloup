<?php

namespace App\Services\Saas;

use App\Models\DomainVerificationLog;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantDomainManager
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function createCustomDomain(Tenant $tenant, array $payload): TenantDomain
    {
        $hostname = $this->normalizeHostname((string) $payload['hostname']);

        $domain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => $hostname,
            'domain_type' => 'custom',
            'is_primary' => false,
            'is_fallback' => false,
            'ssl_status' => 'pending',
            'verification_status' => 'pending',
            'dns_provider' => $payload['dns_provider'] ?? null,
            'dns_setup_mode' => $payload['dns_setup_mode'] ?? 'cname',
            'verification_token' => Str::upper(Str::random(32)),
        ]);

        $this->clearTenantLookupCache($tenant->fresh('domains'));

        return $domain->fresh(['tenant.domains']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function markVerification(TenantDomain $domain, array $payload): TenantDomain
    {
        $domain->loadMissing('tenant.domains');
        $checkType = $this->normalizeCheckType((string) ($payload['check_type'] ?? 'hostname'));

        $domain->forceFill([
            'verification_status' => $payload['verification_status'],
            'ssl_status' => $payload['ssl_status'] ?? $domain->ssl_status,
            'dns_provider' => $payload['dns_provider'] ?? $domain->dns_provider,
            'dns_setup_mode' => $payload['dns_setup_mode'] ?? $domain->dns_setup_mode,
            'cloudflare_zone_id' => $payload['cloudflare_zone_id'] ?? $domain->cloudflare_zone_id,
            'cloudflare_hostname_id' => $payload['cloudflare_hostname_id'] ?? $domain->cloudflare_hostname_id,
            'cloudflare_zone_status' => $payload['cloudflare_zone_status'] ?? $domain->cloudflare_zone_status,
            'cloudflare_name_servers' => $payload['cloudflare_name_servers'] ?? $domain->cloudflare_name_servers,
            'cloudflare_dns_records' => $payload['cloudflare_dns_records'] ?? $domain->cloudflare_dns_records,
            'cloudflare_activated_at' => ($payload['cloudflare_zone_status'] ?? null) === 'active'
                ? ($domain->cloudflare_activated_at ?? now())
                : $domain->cloudflare_activated_at,
            'cloudflare_activation_checked_at' => $payload['cloudflare_activation_checked_at'] ?? $domain->cloudflare_activation_checked_at,
            'verified_at' => $payload['verification_status'] === 'verified' ? now() : null,
            'last_checked_at' => now(),
        ])->save();

        DomainVerificationLog::query()->create([
            'tenant_domain_id' => $domain->id,
            'check_status' => $payload['verification_status'] === 'verified' ? 'success' : 'failed',
            'check_type' => $checkType,
            'message' => $payload['message'] ?? null,
            'payload_json' => $payload['payload_json'] ?? null,
            'checked_at' => now(),
        ]);

        $this->clearTenantLookupCache($domain->tenant);

        return $domain->fresh(['tenant.domains']);
    }

    public function setPrimaryDomain(TenantDomain $domain): TenantDomain
    {
        $domain->loadMissing('tenant.domains');

        if ($domain->domain_type === 'custom' && $domain->verification_status !== 'verified') {
            throw ValidationException::withMessages([
                'domain' => 'Custom domain must be verified before it can become primary.',
            ]);
        }

        DB::transaction(function () use ($domain): void {
            TenantDomain::query()
                ->where('tenant_id', $domain->tenant_id)
                ->update(['is_primary' => false]);

            $domain->forceFill(['is_primary' => true])->save();
        });

        $this->clearTenantLookupCache($domain->tenant->fresh('domains'));

        return $domain->fresh(['tenant.domains']);
    }

    public function restoreFallbackPrimaryIfCustomUnverified(TenantDomain $domain): TenantDomain
    {
        $domain->loadMissing('tenant.domains');

        if (
            $domain->domain_type !== 'custom'
            || !$domain->is_primary
            || $domain->verification_status === 'verified'
        ) {
            return $domain->fresh(['tenant.domains']);
        }

        $fallbackDomain = $domain->tenant?->domains->first(function (TenantDomain $candidate): bool {
            return $candidate->is_fallback && $candidate->domain_type === 'subdomain';
        });

        if (!$fallbackDomain instanceof TenantDomain) {
            return $domain->fresh(['tenant.domains']);
        }

        DB::transaction(function () use ($domain, $fallbackDomain): void {
            TenantDomain::query()
                ->where('tenant_id', $domain->tenant_id)
                ->update(['is_primary' => false]);

            $fallbackDomain->forceFill(['is_primary' => true])->save();
        });

        $this->clearTenantLookupCache($domain->tenant->fresh('domains'));

        return $domain->fresh(['tenant.domains']);
    }

    public function normalizeHostname(string $hostname): string
    {
        $hostname = trim(strtolower($hostname));

        if (str_contains($hostname, '://')) {
            $hostname = (string) parse_url($hostname, PHP_URL_HOST);
        }

        $hostname = preg_replace('/\/.*$/', '', $hostname) ?? $hostname;
        $hostname = preg_replace('/:\d+$/', '', $hostname) ?? $hostname;

        return trim($hostname, '. ');
    }

    public function clearTenantLookupCache(Tenant $tenant): void
    {
        if (!$tenant->relationLoaded('domains')) {
            $tenant->load('domains');
        }

        foreach ($tenant->domains as $domain) {
            Cache::forget('tenant-domain:'.$domain->hostname);
        }

        Cache::forget('tenant-domain:slug:'.$tenant->slug);
    }

    private function normalizeCheckType(string $checkType): string
    {
        return in_array($checkType, ['dns', 'ssl', 'hostname'], true) ? $checkType : 'hostname';
    }
}
