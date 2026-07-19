<?php

namespace App\Http\Controllers\Saas;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\PlatformTenantUpdateRequest;
use App\Models\Customer;
use App\Models\MerchantWallet;
use App\Models\MerchantWithdrawal;
use App\Models\Order;
use App\Models\PlatformAuditLog;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantFeatureFlag;
use App\Models\TenantMember;
use App\Models\TenantPaymentMethod;
use App\Models\User;
use App\Services\Saas\PlatformAuditLogService;
use App\Services\Saas\SubscriptionManagerService;
use App\Services\Saas\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $cleanup = DB::transaction(function () use ($request, $tenantId): array {
            $tenant = $this->findTenant($tenantId);
            $snapshot = $this->serializeTenant($tenant, true);
            $originalSlug = $tenant->slug;
            $domainHosts = $tenant->domains->pluck('hostname')->filter()->values()->all();
            $memberUserIds = $tenant->members->pluck('user_id')->filter()->unique()->values();

            TenantMember::query()->where('tenant_id', $tenant->id)->delete();
            TenantDomain::query()->where('tenant_id', $tenant->id)->delete();

            $tenant->forceFill([
                'name' => 'Deleted Merchant #'.$tenant->id,
                'legal_name' => null,
                'slug' => $this->releasedTenantSlug($tenant),
                'store_code' => $this->releasedStoreCode($tenant),
                'status' => 'archived',
                'contact_email' => null,
                'contact_phone' => null,
                'suspended_at' => null,
            ])->save();

            $tenant->delete();

            $releasedUsers = $this->releaseMerchantUsers($memberUserIds->all());

            $this->platformAuditLogService->log(
                'platform.tenant.deleted',
                'tenant',
                $tenantId,
                $snapshot,
                [
                    'deleted_at' => now()->toDateTimeString(),
                    'reason' => $request->input('reason'),
                    'identity_released' => true,
                    'released_user_ids' => $releasedUsers,
                ],
                $request,
                $request->user(),
                $tenant
            );

            return [
                'slug' => $originalSlug,
                'domain_hosts' => $domainHosts,
                'released_user_ids' => $releasedUsers,
            ];
        });

        $this->forgetTenantRoutingCache($cleanup['slug'], $cleanup['domain_hosts']);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $tenantId,
                'deleted' => true,
                'identity_released' => true,
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
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'block_login' => ['nullable', 'boolean'],
            'hide_products' => ['nullable', 'boolean'],
            'pause_payouts' => ['nullable', 'boolean'],
            'notify_merchant' => ['nullable', 'boolean'],
        ]);
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
            array_merge($tenant->only(array_keys($oldValues)), [
                'reason' => $data['reason'] ?? null,
                'block_login' => $data['block_login'] ?? true,
                'hide_products' => $data['hide_products'] ?? false,
                'pause_payouts' => $data['pause_payouts'] ?? false,
                'notify_merchant' => $data['notify_merchant'] ?? false,
            ]),
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
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'notify_merchant' => ['nullable', 'boolean'],
        ]);
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
            array_merge($tenant->only(array_keys($oldValues)), [
                'reason' => $data['reason'] ?? null,
                'notify_merchant' => $data['notify_merchant'] ?? false,
            ]),
            $request,
            $request->user(),
            $tenant
        );

        return response()->json([
            'status' => true,
            'data' => $this->serializeTenant($tenant->fresh(), true),
        ]);
    }

    public function impersonate(Request $request, int $tenantId): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $tenant = $this->findTenant($tenantId);

        if ($tenant->status !== 'active') {
            return response()->json([
                'message' => 'Only active merchant accounts can be opened from the platform.',
            ], 423);
        }

        $member = $tenant->members
            ->filter(fn (TenantMember $member): bool => $member->status === 'active' && $member->user !== null)
            ->sortBy(fn (TenantMember $member): int => $member->role?->code === 'merchant_owner' ? 0 : 1)
            ->first();

        if (!$member instanceof TenantMember) {
            return response()->json([
                'message' => 'No active merchant member is available for this tenant.',
            ], 422);
        }

        $handoffToken = Str::random(64);
        $expiresAt = now()->addMinutes(2);
        $merchantHost = trim((string) config('saas.merchant_host'), '.');

        Cache::put('merchant-impersonation:'.$handoffToken, [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'user_id' => $member->user_id,
            'actor_user_id' => $request->user()?->id,
            'actor_name' => $request->user()?->name,
            'actor_email' => $request->user()?->email,
            'reason' => $data['reason'] ?? null,
            'created_at' => now()->toDateTimeString(),
            'expires_at' => $expiresAt->toDateTimeString(),
        ], $expiresAt);

        $this->platformAuditLogService->log(
            'platform.tenant.impersonation.created',
            'tenant',
            $tenant->id,
            [],
            [
                'merchant_user_id' => $member->user_id,
                'reason' => $data['reason'] ?? null,
                'expires_at' => $expiresAt->toDateTimeString(),
            ],
            $request,
            $request->user(),
            $tenant
        );

        return response()->json([
            'status' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'expires_at' => $expiresAt,
                'merchant_login_url' => sprintf('https://%s/admin/impersonation/%s', $merchantHost, $handoffToken),
            ],
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
        $payload['control_center'] = $this->merchantControlCenterPayload($tenant, $payload['auto_live_checks']);

        return $payload;
    }

    /**
     * @param  array<string, bool>  $autoLiveChecks
     * @return array<string, mixed>
     */
    private function merchantControlCenterPayload(Tenant $tenant, array $autoLiveChecks): array
    {
        $wallet = MerchantWallet::query()->where('tenant_id', $tenant->id)->first();
        $failedChecks = collect($autoLiveChecks)->filter(fn (bool $status): bool => !$status)->keys()->values();
        $lastSuspension = PlatformAuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('action_code', 'platform.tenant.suspended')
            ->latest('id')
            ->first();

        return [
            'overview_cards' => [
                [
                    'key' => 'total_sales',
                    'label' => 'Total Sales',
                    'value' => (float) ($tenant->completed_sales_total ?? 0),
                    'type' => 'money',
                    'tone' => 'green',
                ],
                [
                    'key' => 'orders',
                    'label' => 'Orders',
                    'value' => (int) ($tenant->orders_count ?? 0),
                    'type' => 'number',
                    'tone' => 'blue',
                ],
                [
                    'key' => 'products',
                    'label' => 'Products',
                    'value' => (int) ($tenant->products_count ?? 0),
                    'type' => 'number',
                    'tone' => 'purple',
                ],
                [
                    'key' => 'balance',
                    'label' => 'Balance',
                    'value' => (float) ($wallet?->available_balance ?? 0),
                    'type' => 'money',
                    'tone' => 'green',
                ],
                [
                    'key' => 'pending_payout',
                    'label' => 'Pending Payout',
                    'value' => (float) ($wallet?->pending_withdrawal_balance ?? 0),
                    'type' => 'money',
                    'tone' => 'orange',
                ],
                [
                    'key' => 'refunds_disputes',
                    'label' => 'Refunds/Disputes',
                    'value' => (float) ($wallet?->total_refunded ?? 0),
                    'type' => 'money',
                    'tone' => 'red',
                ],
            ],
            'profile' => [
                'business_name' => $tenant->name,
                'legal_name' => $tenant->legal_name,
                'email' => $tenant->contact_email,
                'phone' => $tenant->contact_phone,
                'country_code' => $tenant->country_code,
                'locale' => $tenant->primary_locale,
                'currency' => $tenant->primary_currency_code,
                'timezone' => $tenant->timezone,
                'joined_at' => $tenant->created_at,
                'approved_at' => $tenant->approved_at,
                'launched_at' => $tenant->launched_at,
            ],
            'products' => $this->latestProducts($tenant),
            'orders' => $this->latestOrders($tenant),
            'customers' => $this->latestCustomers($tenant),
            'finance' => [
                'wallet' => [
                    'currency_code' => $wallet?->currency_code ?? $tenant->primary_currency_code,
                    'available_balance' => (float) ($wallet?->available_balance ?? 0),
                    'holding_balance' => (float) ($wallet?->holding_balance ?? 0),
                    'pending_withdrawal_balance' => (float) ($wallet?->pending_withdrawal_balance ?? 0),
                    'total_earned' => (float) ($wallet?->total_earned ?? 0),
                    'total_withdrawn' => (float) ($wallet?->total_withdrawn ?? 0),
                    'total_fees' => (float) ($wallet?->total_fees ?? 0),
                    'total_refunded' => (float) ($wallet?->total_refunded ?? 0),
                    'last_settled_at' => $wallet?->last_settled_at,
                ],
                'withdrawals' => [
                    'pending_count' => MerchantWithdrawal::query()->where('tenant_id', $tenant->id)->where('status', 'pending')->count(),
                    'approved_count' => MerchantWithdrawal::query()->where('tenant_id', $tenant->id)->where('status', 'approved')->count(),
                    'rejected_count' => MerchantWithdrawal::query()->where('tenant_id', $tenant->id)->where('status', 'rejected')->count(),
                ],
                'orders' => [
                    'paid_count' => Order::query()->where('tenant_id', $tenant->id)->where('payment_status', PaymentStatus::PAID)->count(),
                    'unpaid_count' => Order::query()->where('tenant_id', $tenant->id)->where('payment_status', PaymentStatus::UNPAID)->count(),
                ],
            ],
            'activity' => $this->latestActivity($tenant),
            'risk' => [
                'status' => $failedChecks->isEmpty() && $tenant->status === 'active' ? 'healthy' : 'review',
                'failed_checks' => $failedChecks,
                'last_suspension_reason' => $lastSuspension?->new_values_json['reason'] ?? null,
                'signals' => [
                    'verified_contact' => filled($tenant->contact_email) || filled($tenant->contact_phone),
                    'verified_domain' => $tenant->domains->contains(fn ($domain): bool => $domain->verification_status === 'verified'),
                    'active_payment_method' => TenantPaymentMethod::query()->where('tenant_id', $tenant->id)->where('status', true)->exists(),
                    'has_products' => (int) ($tenant->products_count ?? 0) > 0,
                    'has_orders' => (int) ($tenant->orders_count ?? 0) > 0,
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestProducts(Tenant $tenant): array
    {
        return Product::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit(8)
            ->get(['id', 'name', 'sku', 'selling_price', 'status', 'created_at', 'updated_at'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->selling_price,
                'status' => (int) $product->status,
                'status_label' => (int) $product->status === Status::ACTIVE ? 'Active' : 'Inactive',
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestOrders(Tenant $tenant): array
    {
        return Order::query()
            ->with('user:id,name,email,phone')
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit(8)
            ->get(['id', 'order_serial_no', 'user_id', 'total', 'payment_status', 'status', 'order_datetime', 'created_at'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_serial_no' => $order->order_serial_no,
                'total' => (float) $order->total,
                'status' => (int) $order->status,
                'status_label' => $this->orderStatusLabel((int) $order->status),
                'payment_status' => (int) $order->payment_status,
                'payment_status_label' => (int) $order->payment_status === PaymentStatus::PAID ? 'Paid' : 'Unpaid',
                'order_datetime' => $order->order_datetime,
                'created_at' => $order->created_at,
                'customer' => $order->user?->only(['id', 'name', 'email', 'phone']),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestCustomers(Tenant $tenant): array
    {
        return Customer::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit(8)
            ->get(['id', 'name', 'email', 'phone', 'country_code', 'status', 'last_login_at', 'created_at'])
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'country_code' => $customer->country_code,
                'status' => (int) $customer->status,
                'last_login_at' => $customer->last_login_at,
                'created_at' => $customer->created_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestActivity(Tenant $tenant): array
    {
        return PlatformAuditLog::query()
            ->with('actor:id,name,email')
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (PlatformAuditLog $log) => [
                'id' => $log->id,
                'action_code' => $log->action_code,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
                'actor' => $log->actor?->only(['id', 'name', 'email']),
                'reason' => $log->new_values_json['reason'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function orderStatusLabel(int $status): string
    {
        return match ($status) {
            OrderStatus::PENDING => 'Pending',
            OrderStatus::CONFIRMED => 'Confirmed',
            OrderStatus::ON_THE_WAY => 'On the way',
            OrderStatus::DELIVERED => 'Delivered',
            OrderStatus::CANCELED => 'Canceled',
            OrderStatus::REJECTED => 'Rejected',
            default => 'Unknown',
        };
    }

    private function releasedTenantSlug(Tenant $tenant): string
    {
        return Str::limit($tenant->slug.'-deleted-'.$tenant->id.'-'.Str::lower(Str::random(8)), 120, '');
    }

    private function releasedStoreCode(Tenant $tenant): string
    {
        return Str::limit($tenant->store_code.'-DEL-'.$tenant->id.'-'.Str::upper(Str::random(4)), 40, '');
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, int>
     */
    private function releaseMerchantUsers(array $userIds): array
    {
        $releasedUserIds = [];

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);

            if (!$user instanceof User) {
                continue;
            }

            if (TenantMember::query()->where('user_id', $user->id)->exists()) {
                continue;
            }

            $hash = Str::lower(Str::random(10));

            $user->tokens()->delete();
            $user->forceFill([
                'name' => 'Deleted Merchant User #'.$user->id,
                'email' => 'deleted-merchant-'.$user->id.'-'.$hash.'@example.invalid',
                'phone' => null,
                'country_code' => null,
                'username' => 'deleted-merchant-'.$user->id.'-'.$hash,
                'status' => Status::INACTIVE,
                'remember_token' => null,
            ])->save();
            $user->delete();

            $releasedUserIds[] = $user->id;
        }

        return $releasedUserIds;
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
