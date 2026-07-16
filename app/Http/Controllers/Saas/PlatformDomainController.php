<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\PlatformDomainVerifyRequest;
use App\Http\Requests\Saas\TenantDomainStoreRequest;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\Saas\PlatformAuditLogService;
use App\Services\Saas\TenantDomainManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformDomainController extends Controller
{
    public function __construct(
        private readonly TenantDomainManager $tenantDomainManager,
        private readonly PlatformAuditLogService $platformAuditLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $domains = TenantDomain::query()
            ->with(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', (int) $request->integer('tenant_id')))
            ->when($request->filled('domain_type'), fn ($query) => $query->where('domain_type', $request->string('domain_type')))
            ->when($request->filled('verification_status'), fn ($query) => $query->where('verification_status', $request->string('verification_status')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $query->where('hostname', 'like', $term);
            })
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $domains->map(fn (TenantDomain $domain) => $this->serializeDomain($domain))->values(),
        ]);
    }

    public function storeForTenant(TenantDomainStoreRequest $request, int $tenantId): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $domain = $this->tenantDomainManager->createCustomDomain($tenant, $request->validated());

        $this->platformAuditLogService->log(
            'platform.domain.created',
            'tenant_domain',
            $domain->id,
            [],
            $domain->only([
                'tenant_id',
                'hostname',
                'domain_type',
                'verification_status',
                'ssl_status',
                'is_primary',
                'is_fallback',
            ]),
            $request,
            $request->user(),
            $tenant
        );

        return response()->json([
            'status' => true,
            'data' => $this->serializeDomain($domain),
        ], 201);
    }

    public function verify(PlatformDomainVerifyRequest $request, int $domainId): JsonResponse
    {
        $domain = TenantDomain::query()->with(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])->findOrFail($domainId);
        $oldValues = $domain->only([
            'verification_status',
            'ssl_status',
            'dns_provider',
            'cloudflare_zone_id',
            'cloudflare_hostname_id',
            'verified_at',
            'last_checked_at',
        ]);

        $domain = $this->tenantDomainManager->markVerification($domain, $request->validated());

        $this->platformAuditLogService->log(
            'platform.domain.verified',
            'tenant_domain',
            $domain->id,
            $oldValues,
            $domain->only(array_keys($oldValues)),
            $request,
            $request->user(),
            $domain->tenant
        );

        return response()->json([
            'status' => true,
            'data' => $this->serializeDomain($domain),
        ]);
    }

    public function setPrimary(Request $request, int $domainId): JsonResponse
    {
        $domain = TenantDomain::query()->with(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])->findOrFail($domainId);
        $oldValues = $domain->only(['is_primary']);

        $domain = $this->tenantDomainManager->setPrimaryDomain($domain);

        $this->platformAuditLogService->log(
            'platform.domain.primary.updated',
            'tenant_domain',
            $domain->id,
            $oldValues,
            $domain->only(array_keys($oldValues)),
            $request,
            $request->user(),
            $domain->tenant
        );

        return response()->json([
            'status' => true,
            'data' => $this->serializeDomain($domain),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDomain(TenantDomain $domain): array
    {
        $domain->loadMissing(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')]);

        $fallbackDomain = $domain->tenant?->domains->firstWhere('is_fallback', true);

        return [
            'id' => $domain->id,
            'tenant_id' => $domain->tenant_id,
            'hostname' => $domain->hostname,
            'domain_type' => $domain->domain_type,
            'is_primary' => $domain->is_primary,
            'is_fallback' => $domain->is_fallback,
            'verification_status' => $domain->verification_status,
            'ssl_status' => $domain->ssl_status,
            'dns_provider' => $domain->dns_provider,
            'verification_token' => $domain->verification_token,
            'verified_at' => $domain->verified_at,
            'last_checked_at' => $domain->last_checked_at,
            'tenant' => $domain->tenant?->only(['id', 'name', 'slug', 'status', 'plan_code']),
            'dns_instructions' => [
                'cname_target' => $fallbackDomain?->hostname,
                'verification_txt_value' => $domain->verification_token,
            ],
        ];
    }
}
