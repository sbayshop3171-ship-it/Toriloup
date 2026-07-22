<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\TenantDomainStoreRequest;
use App\Models\TenantDomain;
use App\Services\Saas\CloudflareDnsService;
use App\Services\Saas\PlatformAuditLogService;
use App\Services\Saas\StorefrontLaunchProbeService;
use App\Services\Saas\SubscriptionManagerService;
use App\Services\Saas\TenantDomainManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MerchantDomainController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantDomainManager $tenantDomainManager,
        private readonly CloudflareDnsService $cloudflareDnsService,
        private readonly StorefrontLaunchProbeService $storefrontLaunchProbeService,
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
        $domain = $this->tenantDomain($domainId);
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

    public function connectCloudflare(Request $request, int $domainId): JsonResponse
    {
        $tenant = $this->tenantContext->current();
        $domain = $this->tenantDomain($domainId);
        $target = $this->fallbackTarget($domain);
        $oldValues = $domain->only([
            'verification_status',
            'ssl_status',
            'dns_provider',
            'cloudflare_zone_id',
            'cloudflare_hostname_id',
            'verified_at',
            'last_checked_at',
            'is_primary',
        ]);

        $result = $this->cloudflareDnsService->connectTenantDomain($domain, $target);
        $launchResult = $this->storefrontLaunchProbeService->probe($domain);

        $domain = $this->tenantDomainManager->markVerification($domain, [
            'verification_status' => ($launchResult['launched'] ?? false) ? 'verified' : 'pending',
            'ssl_status' => ($launchResult['launched'] ?? false) ? 'active' : ($result['ssl_status'] ?? 'pending'),
            'dns_provider' => 'cloudflare',
            'cloudflare_zone_id' => $result['zone_id'] ?? null,
            'cloudflare_hostname_id' => $result['hostname_id'] ?? null,
            'check_type' => $launchResult['check_type'] ?? 'storefront_probe',
            'message' => $launchResult['launched'] ?? false
                ? 'Cloudflare custom hostname is provisioned and the storefront is live.'
                : ($launchResult['message'] ?? 'Cloudflare custom hostname is provisioned, but the storefront is not live on this domain yet.'),
            'payload_json' => [
                'cloudflare_custom_hostname' => $result,
                'launch_probe' => $launchResult,
            ],
        ]);

        if (($launchResult['launched'] ?? false) && $this->shouldAutoPromote($domain)) {
            $domain = $this->tenantDomainManager->setPrimaryDomain($domain);
        }

        $this->platformAuditLogService->log(
            'merchant.domain.cloudflare.connected',
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
            'meta' => [
                'verified' => (bool) ($launchResult['launched'] ?? false),
                'message' => $launchResult['launched'] ?? false
                    ? 'Cloudflare custom hostname connected and storefront launched successfully.'
                    : ($launchResult['message'] ?? 'Cloudflare custom hostname provisioned. Waiting for storefront launch.'),
            ],
        ]);
    }

    public function verify(Request $request, int $domainId): JsonResponse
    {
        $tenant = $this->tenantContext->current();
        $domain = $this->tenantDomain($domainId);
        $target = $this->fallbackTarget($domain);
        $oldValues = $domain->only([
            'verification_status',
            'ssl_status',
            'dns_provider',
            'cloudflare_zone_id',
            'cloudflare_hostname_id',
            'verified_at',
            'last_checked_at',
            'is_primary',
        ]);

        $result = $this->cloudflareDnsService->verifyTenantDomain($domain, $target);
        $launchResult = ($result['verified'] ?? false)
            ? $this->storefrontLaunchProbeService->probe($domain)
            : null;

        $domain = $this->tenantDomainManager->markVerification($domain, [
            'verification_status' => ($result['verified'] ?? false) && ($launchResult['launched'] ?? false) ? 'verified' : 'pending',
            'ssl_status' => ($result['verified'] ?? false) && ($launchResult['launched'] ?? false) ? 'active' : ($domain->ssl_status ?? 'pending'),
            'dns_provider' => 'cloudflare',
            'cloudflare_zone_id' => $domain->cloudflare_zone_id,
            'cloudflare_hostname_id' => $domain->cloudflare_hostname_id,
            'check_type' => ($launchResult['check_type'] ?? null) ?: ($result['check_type'] ?? 'dns'),
            'message' => ($result['verified'] ?? false)
                ? (($launchResult['launched'] ?? false)
                    ? 'DNS verified and the storefront is live on this domain.'
                    : ($launchResult['message'] ?? 'DNS is correct, but the storefront is not live on this domain yet.'))
                : ($result['message'] ?? null),
            'payload_json' => [
                'dns_check' => $result['payload_json'] ?? null,
                'launch_probe' => $launchResult,
            ],
        ]);

        if (($result['verified'] ?? false) && ($launchResult['launched'] ?? false) && $this->shouldAutoPromote($domain)) {
            $domain = $this->tenantDomainManager->setPrimaryDomain($domain);
        }

        $this->platformAuditLogService->log(
            'merchant.domain.verified',
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
            'meta' => [
                'verified' => (bool) (($result['verified'] ?? false) && ($launchResult['launched'] ?? false)),
                'message' => ($result['verified'] ?? false)
                    ? (($launchResult['launched'] ?? false)
                        ? 'DNS verified and storefront launched successfully.'
                        : ($launchResult['message'] ?? 'DNS is correct. Waiting for storefront launch.'))
                    : ($result['message'] ?? null),
            ],
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
            'last_checked_at' => $domain->last_checked_at,
            'cloudflare_zone_id' => $domain->cloudflare_zone_id,
            'cloudflare_hostname_id' => $domain->cloudflare_hostname_id,
            'cloudflare_connect_available' => $this->cloudflareDnsService->isConfigured() && $domain->domain_type === 'custom' && $domain->verification_status !== 'verified',
            'dns_instructions' => [
                'cname_target' => $fallbackDomain?->hostname,
                'record_type' => 'CNAME',
                'proxy_mode' => 'DNS only',
                'verification_txt_value' => $domain->verification_token,
            ],
        ];
    }

    private function tenantDomain(int $domainId): TenantDomain
    {
        $tenant = $this->tenantContext->current();

        return TenantDomain::query()
            ->where('tenant_id', $tenant?->id)
            ->findOrFail($domainId);
    }

    private function fallbackTarget(TenantDomain $domain): string
    {
        $domain->loadMissing(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')]);
        $fallbackDomain = $domain->tenant?->domains->firstWhere('is_fallback', true);

        if ($fallbackDomain === null) {
            throw ValidationException::withMessages([
                'domain' => 'Fallback storefront domain is missing for this tenant.',
            ]);
        }

        return (string) $fallbackDomain->hostname;
    }

    private function shouldAutoPromote(TenantDomain $domain): bool
    {
        $domain->loadMissing(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')]);
        $currentPrimary = $domain->tenant?->domains->firstWhere('is_primary', true);

        if ($currentPrimary === null) {
            return true;
        }

        return $currentPrimary->is_fallback || $currentPrimary->id === $domain->id;
    }
}
