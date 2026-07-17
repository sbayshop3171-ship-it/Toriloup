<?php

namespace App\Http\Controllers\Saas;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\PlatformTenantUpdateRequest;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantFeatureFlag;
use App\Models\TenantMember;
use App\Models\TenantPaymentMethod;
use App\Services\Saas\PlatformAuditLogService;
use App\Services\Saas\SubscriptionManagerService;
use App\Services\Saas\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PlatformTenantController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $tenantProvisioningService,
        private readonly PlatformAuditLogService $platformAuditLogService,
        private readonly SubscriptionManagerService $subscriptionManagerService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::query()
            ->with(['domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])
            ->withCount([
                'members as members_count',
                'members as active_members_count' => fn ($query) => $query->where('status', 'active'),
                'products as products_count',
                'customers as customers_count',
                'orders as orders_count',
                'orders as completed_orders_count' => fn ($query) => $query->where('status', OrderStatus::DELIVERED),
            ])
            ->withSum([
                'orders as completed_sales_total' => fn ($query) => $query->where('status', OrderStatus::DELIVERED),
            ], 'total')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('plan_code'), fn ($query) => $query->where('plan_code', $request->string('plan_code')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($searchQuery) use ($term): void {
                    $searchQuery
                        ->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('contact_email', 'like', $term)
                        ->orWhere('store_code', 'like', $term);
                });
            })
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $tenants->map(fn (Tenant $tenant) => $this->serializeTenant($tenant))->values(),
        ]);
    }

    public function show(int $tenantId): JsonResponse
    {
        $tenant = $this->findTenant($tenantId);

        return response()->json([
            'status' => true,
            'data' => $this->serializeTenant($tenant, true),
        ]);
    }

    public function destroy(Request $request, int $tenantId): JsonResponse
    {
        $tenant = $this->findTenant($tenantId);
        $snapshot = $this->serializeTenant($tenant, true);
        $domainHosts = $tenant->domains->pluck('hostname')->filter()->values()->all();

        TenantDomain::query()->where('tenant_id', $tenant->id)->delete();
        $tenant->delete();

        $this->forgetTenantRoutingCache($tenant->slug, $domainHosts);

        $this->platformAuditLogService->log(
            'platform.tenant.deleted',
            'tenant',
            $tenantId,
            $snapshot,
            ['deleted_at' => now()->toDateTimeString()],
            $request,
            $request->user(),
            $tenant
        );

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $tenantId,
                'deleted' => true,
            ],
        ]);
    }

    public function update(PlatformTenantUpdateRequest $request, int $tenantId): JsonResponse
    {
        $tenant = $this->findTenant($tenantId);
        $oldValues = $tenant->only([
            'name',
            'legal_name',
            'status',
            'plan_code',
            'onboarding_status',
            'primary_locale',
            'primary_currency_code',
            'timezone',
            'country_code',
            'contact_email',
            'contact_phone',
        ]);

        $tenant->fill($request->validated());

        if ($request->filled('status')) {
            if ($tenant->status === 'suspended') {
                $tenant->suspended_at = now();
            } elseif ($tenant->status === 'active') {
                $tenant->suspended_at = null;
            }
        }

        $tenant->save();

        $this->platformAuditLogService->log(
            'platform.tenant.updated',
            'tenant',
            $tenant->id,
            $oldValues,
            $tenant->only(array_keys($oldValues)),
            $request,
            $request->user(),
            $tenant
        );

        return response()->json([
            'status' => true,
            'data' => $this->serializeTenant($tenant->fresh(), true),
        ]);
    }

    public function approve(Request $request, int $tenantId): JsonResponse
    {
        $tenant = $this->findTenant($tenantId);
        $oldValues = $tenant->only(['status', 'approved_by_user_id', 'approved_at', 'launched_at', 'onboarding_status']);

        $tenant->forceFill([
            'status' => 'active',
            'approved_by_user_id' => $request->user()?->id,
            'approved_at' => $tenant->approved_at ?? now(),
            'launched_at' => $tenant->launched_at ?? now(),
            'suspended_at' => null,
            'onboarding_status' => $tenant->onboarding_status === 'pending' ? 'basic_complete' : $tenant->onboarding_status,
        ])->save();

        $this->platformAuditLogService->log(
            'platform.tenant.approved',
            'tenant',
            $tenant->id,
            $oldValues,
            $tenant->only(array_keys($oldValues)),
            $request,
            $request->user(),
            $tenant
        );

        return response()->json([
            'status' => true,
            'data' => $this->serializeTenant($tenant->fresh(), true),
        ]);
    }

    public function suspend(Request $request, int $tenantId): JsonResponse
    {
        $tenant = $this->findTenant($tenantId);
        $oldValues = $tenant->only(['status', 'suspended_at']);

        $tenant->forceFill([
            'status' => 'suspended',
            'suspended_at' => now(),
        ])->save();

        $this->platformAuditLogService->log(
            'platform.tenant.suspended',
            'tenant',
            $tenant->id,
            $oldValues,
            $tenant->only(array_keys($oldValues)),
            $request,
            $request->user(),
            $tenant
        );

        return response()->json([
            'status' => true,
            'data' => $this->serializeTenant($tenant->fresh(), true),
        ]);
    }

    public function reactivate(Request $request, int $tenantId): JsonResponse
    {
        $tenant = $this->findTenant($tenantId);
        $oldValues = $tenant->only(['status', 'suspended_at', 'approved_by_user_id', 'approved_at']);

        $tenant->forceFill([
            'status' => 'active',
            'suspended_at' => null,
            'approved_by_user_id' => $tenant->approved_by_user_id ?? $request->user()?->id,
            'approved_at' => $tenant->approved_at ?? now(),
        ])->save();

        $this->platformAuditLogService->log(
            'platform.tenant.reactivated',
            'tenant',
            $tenant->id,
            $oldValues,
            $tenant->only(array_keys($oldValues)),
            $request,
            $request->user(),
            $tenant
        );

        return response()->json([
            'status' => true,
            'data' => $this->serializeTenant($tenant->fresh(), true),
        ]);
    }

    private function findTenant(int $tenantId): Tenant
    {
        return Tenant::query()
            ->with([
                'domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback'),
                'members.user',
                'members.role',
            ])
            ->withCount([
                'members as members_count',
                'members as active_members_count' => fn ($query) => $query->where('status', 'active'),
                'products as products_count',
                'customers as customers_count',
                'orders as orders_count',
                'orders as completed_orders_count' => fn ($query) => $query->where('status', OrderStatus::DELIVERED),
            ])
            ->withSum([
                'orders as completed_sales_total' => fn ($query) => $query->where('status', OrderStatus::DELIVERED),
            ], 'total')
            ->findOrFail($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTenant(Tenant $tenant, bool $detail = false): array
    {
        $tenant->loadMissing([
            'domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback'),
            'members.user',
            'members.role',
        ]);

        $payload = [
            'id' => $tenant->id,
            'uuid' => $tenant->uuid,
            'name' => $tenant->name,
            'legal_name' => $tenant->legal_name,
            'slug' => $tenant->slug,
            'store_code' => $tenant->store_code,
            'status' => $tenant->status,
            'plan_code' => $tenant->plan_code,
            'onboarding_status' => $tenant->onboarding_status,
            'created_at' => $tenant->created_at,
            'primary_locale' => $tenant->primary_locale,
            'primary_currency_code' => $tenant->primary_currency_code,
            'timezone' => $tenant->timezone,
            'country_code' => $tenant->country_code,
            'contact_email' => $tenant->contact_email,
            'contact_phone' => $tenant->contact_phone,
            'approved_at' => $tenant->approved_at,
            'launched_at' => $tenant->launched_at,
            'suspended_at' => $tenant->suspended_at,
            'storefront_hostname' => $this->fallbackStorefrontHostname($tenant),
            'storefront_url' => 'https://'.$this->fallbackStorefrontHostname($tenant),
            'primary_domain' => $tenant->domains->first()?->hostname,
            'domains' => $tenant->domains->map(fn ($domain) => [
                'id' => $domain->id,
                'hostname' => $domain->hostname,
                'domain_type' => $domain->domain_type,
                'is_primary' => $domain->is_primary,
                'is_fallback' => $domain->is_fallback,
                'verification_status' => $domain->verification_status,
                'ssl_status' => $domain->ssl_status,
            ])->values(),
            'members_count' => $tenant->members_count ?? $tenant->members->count(),
            'active_members_count' => $tenant->active_members_count ?? $tenant->members->where('status', 'active')->count(),
            'products_count' => (int) ($tenant->products_count ?? 0),
            'customers_count' => (int) ($tenant->customers_count ?? 0),
            'orders_count' => (int) ($tenant->orders_count ?? 0),
            'completed_orders_count' => (int) ($tenant->completed_orders_count ?? 0),
            'completed_sales_total' => (float) ($tenant->completed_sales_total ?? 0),
        ];

        if (!$detail) {
            return $payload;
        }

        $payload['members'] = $tenant->members->map(fn (TenantMember $member) => [
            'id' => $member->id,
            'status' => $member->status,
            'joined_at' => $member->joined_at,
            'user' => $member->user?->only(['id', 'name', 'email', 'phone', 'country_code', 'status']),
            'role' => $member->role?->only(['id', 'code', 'name', 'scope']),
        ])->values();
        $payload['payment_methods'] = TenantPaymentMethod::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (TenantPaymentMethod $method) => [
                'id' => $method->id,
                'provider_code' => $method->provider_code,
                'display_name' => $method->display_name,
                'status' => $method->status,
                'checkout_label' => $method->checkout_label,
                'fee_type' => $method->fee_type,
                'fee_value' => $method->fee_value,
                'sort_order' => $method->sort_order,
                'config_json' => $method->config_json,
            ])
            ->values();
        $payload['feature_flags'] = TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->map(fn (TenantFeatureFlag $flag) => [
                'id' => $flag->id,
                'feature_code' => $flag->feature_code,
                'status' => $flag->status,
                'source' => $flag->source,
            ])
            ->values();
        $payload['auto_live_checks'] = $this->tenantProvisioningService->evaluateAutoLiveChecks($tenant);
        $currentSubscription = $this->subscriptionManagerService->currentSubscription($tenant);
        $payload['subscription'] = $currentSubscription ? $this->subscriptionManagerService->serializeSubscription($currentSubscription) : null;
        $payload['usage_summary'] = $this->subscriptionManagerService->usageSummary($tenant);

        return $payload;
    }

    private function fallbackStorefrontHostname(Tenant $tenant): string
    {
        return $tenant->slug.'.'.trim((string) config('saas.fallback_subdomain_suffix', 'toriloup.com'), '.');
    }

    /**
     * @param  array<int, string>  $hosts
     */
    private function forgetTenantRoutingCache(string $slug, array $hosts): void
    {
        if (!config('tenancy.cache.enabled', true)) {
            return;
        }

        foreach ($hosts as $host) {
            Cache::forget('tenant-domain:'.$host);
        }

        Cache::forget('tenant-domain:slug:'.$slug);
    }
}
