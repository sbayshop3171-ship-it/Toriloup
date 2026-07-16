<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\TenantDomainStoreRequest;
use App\Models\TenantDomain;
use App\Services\Saas\PlatformAuditLogService;
use App\Services\Saas\SubscriptionManagerService;
use App\Services\Saas\TenantDomainManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantDomainController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantDomainManager $tenantDomainManager,
        private readonly PlatformAuditLogService $platformAuditLogService,
        private readonly SubscriptionManagerService $subscriptionManagerService,
    ) {
    }

    public function index(): JsonResponse
    {
        $tenant = $this->tenantContext->current();

        $domains = TenantDomain::query()
            ->where('tenant_id', $tenant?->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('is_fallback')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $domains->map(fn (TenantDomain $domain) => $this->serializeDomain($domain))->values(),
        ]);
    }

    public function store(TenantDomainStoreRequest $request): JsonResponse
    {
        $tenant = $this->tenantContext->current();

        if ($tenant !== null) {
            $this->subscriptionManagerService->enforceLimit($tenant, 'custom_domains', 1, 'Your current plan custom domain limit has been reached.');
        }

        $domain = $this->tenantDomainManager->createCustomDomain($tenant, $request->validated());

        $this->platformAuditLogService->log(
            'merchant.domain.requested',
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
            $tenant,
            'merchant'
        );

        return response()->json([
            'status' => true,
            'data' => $this->serializeDomain($domain),
        ], 201);
    }

    public function setPrimary(Request $request, int $domainId): JsonResponse
    {
        $tenant = $this->tenantContext->current();
        $domain = TenantDomain::query()
            ->where('tenant_id', $tenant?->id)
            ->findOrFail($domainId);
        $oldValues = $domain->only(['is_primary']);

        $domain = $this->tenantDomainManager->setPrimaryDomain($domain);

        $this->platformAuditLogService->log(
            'merchant.domain.primary.updated',
            'tenant_domain',
            $domain->id,
            $oldValues,
            $domain->only(array_keys($oldValues)),
            $request,
            $request->user(),
            $tenant,
            'merchant'
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
        $tenant = $domain->tenant()->with(['domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])->first();
        $fallbackDomain = $tenant?->domains->firstWhere('is_fallback', true);

        return [
            'id' => $domain->id,
            'hostname' => $domain->hostname,
            'domain_type' => $domain->domain_type,
            'is_primary' => $domain->is_primary,
            'is_fallback' => $domain->is_fallback,
            'verification_status' => $domain->verification_status,
            'ssl_status' => $domain->ssl_status,
            'verification_token' => $domain->verification_token,
            'dns_provider' => $domain->dns_provider,
            'verified_at' => $domain->verified_at,
            'dns_instructions' => [
                'cname_target' => $fallbackDomain?->hostname,
                'verification_txt_value' => $domain->verification_token,
            ],
        ];
    }
}
