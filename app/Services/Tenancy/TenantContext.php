<?php

namespace App\Services\Tenancy;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantContext
{
    public function current(?Request $request = null): ?Tenant
    {
        $request ??= app()->bound('request') ? request() : null;
        $tenantAttribute = config('tenancy.tenant_request_attribute', 'saas.tenant');

        $tenant = $request?->attributes->get($tenantAttribute);

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        $hasTenantSignal = filled($request?->header('X-Tenant-Slug'))
            || filled($request?->query('tenant_slug'));

        if ($hasTenantSignal) {
            $tenant = $this->resolveForAuthenticatedUser($request);

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        $tenant = $this->resolveFromHost($request);

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        if (!$request instanceof Request && app()->bound('currentTenant')) {
            $tenant = app('currentTenant');

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        return $this->resolveForAuthenticatedUser($request);
    }

    public function currentId(?Request $request = null): ?int
    {
        return $this->current($request)?->getKey();
    }

    public function currentDomain(?Request $request = null): ?TenantDomain
    {
        $request ??= app()->bound('request') ? request() : null;
        $domainAttribute = config('tenancy.tenant_domain_attribute', 'saas.tenant_domain');
        $domain = $request?->attributes->get($domainAttribute);

        if ($domain instanceof TenantDomain) {
            return $domain;
        }

        $domain = $this->resolveDomainFromHost($request);

        if ($domain instanceof TenantDomain) {
            $this->set($domain->tenant, $domain, $request);

            return $domain;
        }

        if (!$request instanceof Request && app()->bound('currentTenantDomain')) {
            $domain = app('currentTenantDomain');

            if ($domain instanceof TenantDomain) {
                return $domain;
            }
        }

        return null;
    }

    public function set(Tenant $tenant, ?TenantDomain $domain = null, ?Request $request = null): void
    {
        $request ??= request();

        app()->instance('currentTenant', $tenant);

        if ($request !== null) {
            $request->attributes->set(config('tenancy.tenant_request_attribute', 'saas.tenant'), $tenant);
        }

        if ($domain !== null) {
            app()->instance('currentTenantDomain', $domain);

            if ($request !== null) {
                $request->attributes->set(config('tenancy.tenant_domain_attribute', 'saas.tenant_domain'), $domain);
            }
        }
    }

    public function hydrateFromRequest(Request $request): ?Tenant
    {
        $tenant = $this->current($request);

        if ($tenant === null) {
            return null;
        }

        $domain = $this->currentDomain($request);

        if ($domain === null) {
            $domain = $tenant->domains()
                ->orderByDesc('is_primary')
                ->orderByDesc('is_fallback')
                ->first();
        }

        $this->set($tenant, $domain, $request);

        return $tenant;
    }

    private function resolveForAuthenticatedUser(?Request $request = null): ?Tenant
    {
        $user = $request?->user() ?? Auth::user();

        if ($user === null) {
            return null;
        }

        if ((int) $user->myRole === Role::ADMIN) {
            return null;
        }

        $tenantSlug = $request?->header('X-Tenant-Slug') ?: $request?->query('tenant_slug');

        $membershipQuery = TenantMember::query()
            ->with(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])
            ->where('user_id', $user->id)
            ->where('status', 'active');

        if (filled($tenantSlug)) {
            $membershipQuery->whereHas('tenant', fn ($query) => $query->where('slug', $tenantSlug));
        }

        return $membershipQuery->first()?->tenant;
    }

    private function resolveFromHost(?Request $request): ?Tenant
    {
        $domain = $this->resolveDomainFromHost($request);

        if (!$domain instanceof TenantDomain) {
            return null;
        }

        $this->set($domain->tenant, $domain, $request);

        return $domain->tenant;
    }

    private function resolveDomainFromHost(?Request $request): ?TenantDomain
    {
        if (!$request instanceof Request) {
            return null;
        }

        return app(TenantResolver::class)->resolveFromRequest($request);
    }
}
