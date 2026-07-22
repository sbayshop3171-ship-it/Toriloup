<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\TenantDomainStoreRequest;
use App\Models\TenantDomain;
use App\Services\Saas\CloudflareDnsService;
use App\Services\Saas\FastPanelSiteAliasService;
use App\Services\Saas\PlatformAuditLogService;
use App\Services\Saas\StorefrontLaunchProbeService;
use App\Services\Saas\SubscriptionManagerService;
use App\Services\Saas\TenantDomainManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class MerchantDomainController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantDomainManager $tenantDomainManager,
        private readonly CloudflareDnsService $cloudflareDnsService,
        private readonly FastPanelSiteAliasService $fastPanelSiteAliasService,
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
                'dns_setup_mode',
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

        if ($this->usesFullZoneSetup($domain)) {
            return $this->connectCloudflareZone($request, $domain);
        }

        $target = $this->fallbackTarget($domain);
        $oldValues = $this->domainAutomationValues($domain);

        $result = $this->cloudflareDnsService->connectTenantDomain($domain, $target);
        $storefrontAlias = $this->ensureStorefrontAlias($domain);
        $verified = $this->customHostnameReady($result);
        $launchResult = $verified ? $this->storefrontLaunchProbeService->probe($domain) : null;

        $domain = $this->tenantDomainManager->markVerification($domain, [
            'verification_status' => $verified ? 'verified' : 'pending',
            'ssl_status' => $verified ? 'active' : ($result['ssl_status'] ?? 'pending'),
            'dns_provider' => 'cloudflare',
            'cloudflare_zone_id' => $result['zone_id'] ?? null,
            'cloudflare_hostname_id' => $result['hostname_id'] ?? null,
            'check_type' => $launchResult['check_type'] ?? 'storefront_probe',
            'message' => $verified && ($launchResult['launched'] ?? false)
                ? 'Cloudflare custom hostname is provisioned and the storefront is live.'
                : ($verified
                    ? 'Cloudflare custom hostname is verified. Storefront launch probe has not passed yet.'
                    : 'Cloudflare custom hostname is provisioned and waiting for DNS/SSL verification.'),
            'payload_json' => [
                'cloudflare_custom_hostname' => $result,
                'fastpanel_alias' => $storefrontAlias,
                'launch_probe' => $launchResult,
            ],
        ]);

        if ($verified && $this->shouldAutoPromote($domain)) {
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
                'verified' => $verified,
                'storefront_alias_ready' => (bool) ($storefrontAlias['ensured'] ?? false),
                'storefront_launched' => (bool) ($launchResult['launched'] ?? false),
                'message' => $verified && ($launchResult['launched'] ?? false)
                    ? 'Cloudflare custom hostname connected and storefront launched successfully.'
                    : ($verified
                        ? 'Cloudflare custom hostname verified. Storefront launch is still warming up.'
                        : 'Cloudflare custom hostname provisioned. Waiting for DNS/SSL verification.'),
            ],
        ]);
    }

    public function verify(Request $request, int $domainId): JsonResponse
    {
        $tenant = $this->tenantContext->current();
        $domain = $this->tenantDomain($domainId);

        if ($this->usesFullZoneSetup($domain)) {
            return $this->verifyCloudflareZone($request, $domain);
        }

        $target = $this->fallbackTarget($domain);
        $oldValues = $this->domainAutomationValues($domain);

        $result = $this->cloudflareDnsService->verifyTenantDomain($domain, $target);
        $verified = (bool) ($result['verified'] ?? false);
        $storefrontAlias = $verified ? $this->ensureStorefrontAlias($domain) : null;
        $launchResult = $verified ? $this->storefrontLaunchProbeService->probe($domain) : null;

        $domain = $this->tenantDomainManager->markVerification($domain, [
            'verification_status' => $verified ? 'verified' : 'pending',
            'ssl_status' => $verified ? 'active' : ($domain->ssl_status ?? 'pending'),
            'dns_provider' => 'cloudflare',
            'cloudflare_zone_id' => $domain->cloudflare_zone_id,
            'cloudflare_hostname_id' => $domain->cloudflare_hostname_id,
            'check_type' => ($launchResult['check_type'] ?? null) ?: ($result['check_type'] ?? 'dns'),
            'message' => $verified
                ? (($launchResult['launched'] ?? false)
                    ? 'DNS verified and the storefront is live on this domain.'
                    : 'DNS/custom hostname is verified. Storefront launch probe has not passed yet.')
                : ($result['message'] ?? null),
            'payload_json' => [
                'dns_check' => $result['payload_json'] ?? null,
                'fastpanel_alias' => $storefrontAlias,
                'launch_probe' => $launchResult,
            ],
        ]);

        if ($verified && $this->shouldAutoPromote($domain)) {
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
                'verified' => $verified,
                'storefront_alias_ready' => (bool) ($storefrontAlias['ensured'] ?? false),
                'storefront_launched' => (bool) ($launchResult['launched'] ?? false),
                'message' => $verified
                    ? (($launchResult['launched'] ?? false)
                        ? 'DNS verified and storefront launched successfully.'
                        : 'DNS/custom hostname verified. Storefront launch is still warming up.')
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
            'dns_setup_mode' => $domain->dns_setup_mode ?: 'cname',
            'verified_at' => $domain->verified_at,
            'last_checked_at' => $domain->last_checked_at,
            'cloudflare_zone_id' => $domain->cloudflare_zone_id,
            'cloudflare_hostname_id' => $domain->cloudflare_hostname_id,
            'cloudflare_zone_status' => $domain->cloudflare_zone_status,
            'cloudflare_name_servers' => $domain->cloudflare_name_servers ?? [],
            'cloudflare_dns_records' => $domain->cloudflare_dns_records ?? [],
            'cloudflare_activated_at' => $domain->cloudflare_activated_at,
            'cloudflare_activation_checked_at' => $domain->cloudflare_activation_checked_at,
            'cloudflare_connect_available' => $this->cloudflareConnectAvailable($domain),
            'cloudflare_full_zone_available' => $this->usesFullZoneSetup($domain)
                ? $this->cloudflareDnsService->isFullZoneConfigured()
                : false,
            'dns_instructions' => $this->dnsInstructions($domain, $fallbackDomain),
        ];
    }

    private function connectCloudflareZone(Request $request, TenantDomain $domain): JsonResponse
    {
        $tenant = $this->tenantContext->current();
        $target = $this->fallbackTarget($domain);
        $oldValues = $this->domainAutomationValues($domain);
        $result = $this->cloudflareDnsService->connectTenantZone($domain, $target);
        $storefrontAlias = $this->ensureStorefrontAlias($domain);
        $zoneActive = (bool) ($result['verified'] ?? false);
        $launchResult = $zoneActive ? $this->storefrontLaunchProbeService->probe($domain) : null;
        $verified = $zoneActive && (bool) ($launchResult['launched'] ?? false);

        $domain = $this->tenantDomainManager->markVerification($domain, [
            'verification_status' => $verified ? 'verified' : 'pending',
            'ssl_status' => $verified ? 'active' : 'pending',
            'dns_provider' => 'cloudflare',
            'dns_setup_mode' => 'full_zone',
            'cloudflare_zone_id' => $result['zone_id'] ?? null,
            'cloudflare_zone_status' => $result['zone_status'] ?? null,
            'cloudflare_name_servers' => $result['name_servers'] ?? [],
            'cloudflare_dns_records' => $result['dns_records'] ?? [],
            'cloudflare_activation_checked_at' => now(),
            'check_type' => 'dns',
            'message' => $this->fullZoneMessage($verified, $zoneActive, $result, $launchResult),
            'payload_json' => [
                'cloudflare_full_zone' => $result,
                'fastpanel_alias' => $storefrontAlias,
                'launch_probe' => $launchResult,
            ],
        ]);

        if ($verified && $this->shouldAutoPromote($domain)) {
            $domain = $this->tenantDomainManager->setPrimaryDomain($domain);
        }

        $this->platformAuditLogService->log(
            'merchant.domain.cloudflare.zone.connected',
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
                'verified' => $verified,
                'zone_active' => $zoneActive,
                'storefront_alias_ready' => (bool) ($storefrontAlias['ensured'] ?? false),
                'storefront_launched' => (bool) ($launchResult['launched'] ?? false),
                'nameservers' => $result['name_servers'] ?? [],
                'message' => $this->fullZoneMessage($verified, $zoneActive, $result, $launchResult),
            ],
        ]);
    }

    private function verifyCloudflareZone(Request $request, TenantDomain $domain): JsonResponse
    {
        $tenant = $this->tenantContext->current();
        $target = $this->fallbackTarget($domain);
        $oldValues = $this->domainAutomationValues($domain);
        $result = $this->cloudflareDnsService->verifyTenantZone($domain, $target);
        $zoneActive = (bool) ($result['verified'] ?? false);
        $storefrontAlias = $zoneActive ? $this->ensureStorefrontAlias($domain) : null;
        $launchResult = $zoneActive ? $this->storefrontLaunchProbeService->probe($domain) : null;
        $verified = $zoneActive && (bool) ($launchResult['launched'] ?? false);

        $domain = $this->tenantDomainManager->markVerification($domain, [
            'verification_status' => $verified ? 'verified' : 'pending',
            'ssl_status' => $verified ? 'active' : 'pending',
            'dns_provider' => 'cloudflare',
            'dns_setup_mode' => 'full_zone',
            'cloudflare_zone_id' => $result['zone_id'] ?? $domain->cloudflare_zone_id,
            'cloudflare_zone_status' => $result['zone_status'] ?? $domain->cloudflare_zone_status,
            'cloudflare_name_servers' => $result['name_servers'] ?? $domain->cloudflare_name_servers,
            'cloudflare_dns_records' => $result['dns_records'] ?? $domain->cloudflare_dns_records,
            'cloudflare_activation_checked_at' => now(),
            'check_type' => 'dns',
            'message' => $this->fullZoneMessage($verified, $zoneActive, $result, $launchResult),
            'payload_json' => [
                'cloudflare_full_zone' => $result,
                'fastpanel_alias' => $storefrontAlias,
                'launch_probe' => $launchResult,
            ],
        ]);

        if ($verified && $this->shouldAutoPromote($domain)) {
            $domain = $this->tenantDomainManager->setPrimaryDomain($domain);
        }

        $this->platformAuditLogService->log(
            'merchant.domain.cloudflare.zone.verified',
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
                'verified' => $verified,
                'zone_active' => $zoneActive,
                'storefront_alias_ready' => (bool) ($storefrontAlias['ensured'] ?? false),
                'storefront_launched' => (bool) ($launchResult['launched'] ?? false),
                'nameservers' => $result['name_servers'] ?? [],
                'message' => $this->fullZoneMessage($verified, $zoneActive, $result, $launchResult),
            ],
        ]);
    }

    private function tenantDomain(int $domainId): TenantDomain
    {
        $tenant = $this->tenantContext->current();

        return TenantDomain::query()
            ->where('tenant_id', $tenant?->id)
            ->findOrFail($domainId);
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureStorefrontAlias(TenantDomain $domain): array
    {
        try {
            return $this->fastPanelSiteAliasService->ensureStorefrontAlias((string) $domain->hostname);
        } catch (Throwable $exception) {
            return [
                'configured' => $this->fastPanelSiteAliasService->isConfigured(),
                'ensured' => false,
                'aliases_added' => [],
                'aliases_present' => [],
                'message' => $exception->getMessage(),
            ];
        }
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

    private function usesFullZoneSetup(TenantDomain $domain): bool
    {
        return ($domain->dns_setup_mode ?: 'cname') === 'full_zone';
    }

    private function cloudflareConnectAvailable(TenantDomain $domain): bool
    {
        if ($domain->domain_type !== 'custom' || $domain->verification_status === 'verified') {
            return false;
        }

        return $this->usesFullZoneSetup($domain)
            ? $this->cloudflareDnsService->isFullZoneConfigured()
            : $this->cloudflareDnsService->isConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    private function dnsInstructions(TenantDomain $domain, ?TenantDomain $fallbackDomain): array
    {
        if ($this->usesFullZoneSetup($domain)) {
            return [
                'setup_mode' => 'full_zone',
                'record_type' => 'NS',
                'name_servers' => $domain->cloudflare_name_servers ?? [],
                'zone_status' => $domain->cloudflare_zone_status,
                'cname_target' => $fallbackDomain?->hostname,
                'proxy_mode' => config('cloudflare.full_zone.proxy_records', true) ? 'Proxied' : 'DNS only',
                'verification_txt_value' => $domain->verification_token,
                'registrar_action' => 'Replace the current nameservers at the domain registrar with the Cloudflare nameservers shown here.',
            ];
        }

        return [
            'setup_mode' => 'cname',
            'cname_target' => $fallbackDomain?->hostname,
            'record_type' => 'CNAME',
            'proxy_mode' => 'DNS only',
            'verification_txt_value' => $domain->verification_token,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function domainAutomationValues(TenantDomain $domain): array
    {
        return $domain->only([
            'verification_status',
            'ssl_status',
            'dns_provider',
            'dns_setup_mode',
            'cloudflare_zone_id',
            'cloudflare_hostname_id',
            'cloudflare_zone_status',
            'cloudflare_name_servers',
            'cloudflare_dns_records',
            'cloudflare_activated_at',
            'cloudflare_activation_checked_at',
            'verified_at',
            'last_checked_at',
            'is_primary',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $launchResult
     * @param  array<string, mixed>  $result
     */
    private function fullZoneMessage(bool $verified, bool $zoneActive, array $result, ?array $launchResult): string
    {
        if ($verified) {
            return 'Cloudflare nameservers are active and the storefront launched successfully.';
        }

        if ($zoneActive) {
            return 'Cloudflare nameservers are active. SSL or storefront routing is still warming up, so check again shortly.';
        }

        return (string) ($result['message'] ?? 'Replace nameservers at the registrar, then check again.');
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

    /**
     * @param  array<string, mixed>  $result
     */
    private function customHostnameReady(array $result): bool
    {
        $status = strtolower((string) ($result['status'] ?? ''));
        $sslStatus = strtolower((string) ($result['ssl_status'] ?? ''));

        return in_array($status, ['active', 'verified', 'deployed'], true)
            || in_array($sslStatus, ['active', 'verified'], true);
    }
}
