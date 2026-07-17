<?php

namespace App\Services\Saas;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PlatformAuditLog;
use App\Models\PlatformProvider;
use App\Models\PlatformSupportSession;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\TenantSubscription;
use App\Models\TenantSubscriptionInvoice;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class PlatformTenantInsightService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $tenants = Tenant::query()
            ->with(['domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])
            ->get();

        $metricsMap = $this->metricsMap($tenants->pluck('id')->all());
        $today = now()->startOfDay();
        $monthStart = now()->copy()->startOfMonth();

        return [
            'summary' => [
                'merchants_total' => $tenants->count(),
                'merchants_active' => $tenants->where('status', 'active')->count(),
                'merchants_draft' => $tenants->where('status', 'draft')->count(),
                'merchants_suspended' => $tenants->where('status', 'suspended')->count(),
                'products_total' => Product::withoutGlobalScopes()->count(),
                'customers_total' => $this->uniqueCustomerCount(),
                'gmv_total' => (float) Order::withoutGlobalScopes()
                    ->where('payment_status', PaymentStatus::PAID)
                    ->sum('total'),
                'orders_total' => Order::withoutGlobalScopes()->count(),
                'new_signups_today' => $tenants->filter(fn (Tenant $tenant) => optional($tenant->created_at)?->greaterThanOrEqualTo($today))->count(),
                'new_signups_this_month' => $tenants->filter(fn (Tenant $tenant) => optional($tenant->created_at)?->greaterThanOrEqualTo($monthStart))->count(),
                'live_domains' => TenantDomain::query()
                    ->where('verification_status', 'verified')
                    ->where('ssl_status', 'active')
                    ->count(),
                'pending_domains' => TenantDomain::query()
                    ->where('domain_type', 'custom')
                    ->where('verification_status', 'pending')
                    ->count(),
                'active_subscriptions' => TenantSubscription::query()->whereIn('status', ['trialing', 'active'])->count(),
                'failed_renewals' => TenantSubscription::query()->where('status', 'past_due')->count(),
                'tenants_live' => $tenants->where('onboarding_status', 'live')->count(),
                'tenants_onboarding' => $tenants->whereNotIn('onboarding_status', ['live', 'completed'])->count(),
                'provider_issues' => PlatformProvider::query()->where('status', false)->count(),
                'support_sessions_active' => PlatformSupportSession::query()->whereIn('status', ['pending', 'active'])->count(),
                'orders_today' => Order::withoutGlobalScopes()->whereDate('order_datetime', now()->toDateString())->count(),
                'gmv_today' => (float) Order::withoutGlobalScopes()
                    ->whereDate('order_datetime', now()->toDateString())
                    ->where('payment_status', PaymentStatus::PAID)
                    ->sum('total'),
                'tenants_total' => $tenants->count(),
                'tenants_active' => $tenants->where('status', 'active')->count(),
                'tenants_draft' => $tenants->where('status', 'draft')->count(),
                'tenants_suspended' => $tenants->where('status', 'suspended')->count(),
                'custom_domains_pending' => TenantDomain::query()->where('domain_type', 'custom')->where('verification_status', 'pending')->count(),
                'custom_domains_verified' => TenantDomain::query()->where('domain_type', 'custom')->where('verification_status', 'verified')->count(),
                'domain_issues' => TenantDomain::query()
                    ->where('domain_type', 'custom')
                    ->where(function ($query): void {
                        $query->whereIn('verification_status', ['failed', 'rejected'])
                            ->orWhereIn('ssl_status', ['failed', 'expired']);
                    })
                    ->count(),
                'merchant_memberships_active' => TenantMember::query()->where('status', 'active')->count(),
                'subscriptions_active' => TenantSubscription::query()->where('status', 'active')->count(),
                'support_alerts' => PlatformSupportSession::query()->whereIn('status', ['pending', 'active'])->count(),
            ],
            'merchant_growth' => $this->merchantGrowthSeries(),
            'sales_trend' => $this->salesTrendSeries(),
            'top_merchants' => $this->topMerchants($tenants, $metricsMap),
            'merchants_needing_action' => $this->attentionMerchants($tenants, $metricsMap),
            'latest_audit_events' => $this->latestAuditEvents(),
        ];
    }

    /**
     * @param  array<int, int>  $tenantIds
     * @return array<int, array<string, mixed>>
     */
    public function metricsMap(array $tenantIds): array
    {
        $tenantIds = array_values(array_unique(array_map('intval', $tenantIds)));

        if ($tenantIds === []) {
            return [];
        }

        $metrics = [];
        foreach ($tenantIds as $tenantId) {
            $metrics[$tenantId] = [
                'products_count' => 0,
                'customers_count' => 0,
                'total_orders_count' => 0,
                'completed_orders_count' => 0,
                'gmv_total' => 0.0,
                'last_order_at' => null,
                'last_member_seen_at' => null,
                'last_customer_login_at' => null,
                'pending_custom_domains' => 0,
                'verified_domains_count' => 0,
                'active_support_sessions_count' => 0,
                'has_past_due_subscription' => false,
            ];
        }

        Product::withoutGlobalScopes()
            ->selectRaw('tenant_id, COUNT(*) AS aggregate_value')
            ->whereIn('tenant_id', $tenantIds)
            ->groupBy('tenant_id')
            ->get()
            ->each(fn ($row) => $metrics[(int) $row->tenant_id]['products_count'] = (int) $row->aggregate_value);

        Customer::withoutGlobalScopes()
            ->selectRaw('tenant_id, COUNT(*) AS aggregate_value, MAX(last_login_at) AS last_login_at')
            ->whereIn('tenant_id', $tenantIds)
            ->groupBy('tenant_id')
            ->get()
            ->each(function ($row) use (&$metrics): void {
                $tenantId = (int) $row->tenant_id;
                $metrics[$tenantId]['customers_count'] = (int) $row->aggregate_value;
                $metrics[$tenantId]['last_customer_login_at'] = $row->last_login_at;
            });

        Order::withoutGlobalScopes()
            ->selectRaw(
                'tenant_id, COUNT(*) AS total_orders_count, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed_orders_count, '.
                'SUM(CASE WHEN payment_status = ? THEN total ELSE 0 END) AS gmv_total, '.
                'MAX(order_datetime) AS last_order_at',
                [OrderStatus::DELIVERED, PaymentStatus::PAID]
            )
            ->whereIn('tenant_id', $tenantIds)
            ->groupBy('tenant_id')
            ->get()
            ->each(function ($row) use (&$metrics): void {
                $tenantId = (int) $row->tenant_id;
                $metrics[$tenantId]['total_orders_count'] = (int) $row->total_orders_count;
                $metrics[$tenantId]['completed_orders_count'] = (int) $row->completed_orders_count;
                $metrics[$tenantId]['gmv_total'] = (float) $row->gmv_total;
                $metrics[$tenantId]['last_order_at'] = $row->last_order_at;
            });

        TenantMember::query()
            ->selectRaw('tenant_id, MAX(last_seen_at) AS last_seen_at')
            ->whereIn('tenant_id', $tenantIds)
            ->groupBy('tenant_id')
            ->get()
            ->each(fn ($row) => $metrics[(int) $row->tenant_id]['last_member_seen_at'] = $row->last_seen_at);

        TenantDomain::query()
            ->selectRaw(
                "tenant_id, ".
                "SUM(CASE WHEN domain_type = 'custom' AND verification_status = 'pending' THEN 1 ELSE 0 END) AS pending_custom_domains, ".
                "SUM(CASE WHEN verification_status = 'verified' AND ssl_status = 'active' THEN 1 ELSE 0 END) AS verified_domains_count"
            )
            ->whereIn('tenant_id', $tenantIds)
            ->groupBy('tenant_id')
            ->get()
            ->each(function ($row) use (&$metrics): void {
                $tenantId = (int) $row->tenant_id;
                $metrics[$tenantId]['pending_custom_domains'] = (int) $row->pending_custom_domains;
                $metrics[$tenantId]['verified_domains_count'] = (int) $row->verified_domains_count;
            });

        PlatformSupportSession::query()
            ->selectRaw("tenant_id, COUNT(*) AS aggregate_value")
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('status', ['pending', 'active'])
            ->groupBy('tenant_id')
            ->get()
            ->each(fn ($row) => $metrics[(int) $row->tenant_id]['active_support_sessions_count'] = (int) $row->aggregate_value);

        TenantSubscription::query()
            ->select(['tenant_id', 'status'])
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('tenant_id')
            ->each(function (Collection $subscriptions, int $tenantId) use (&$metrics): void {
                $latest = $subscriptions->first();
                $metrics[$tenantId]['has_past_due_subscription'] = $latest?->status === 'past_due';
            });

        return $metrics;
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @param  array<int, array<string, mixed>>  $metricsMap
     * @return array<int, array<string, mixed>>
     */
    public function topMerchants(Collection $tenants, array $metricsMap, int $limit = 6): array
    {
        return $tenants
            ->map(function (Tenant $tenant) use ($metricsMap): array {
                $metrics = $metricsMap[$tenant->id] ?? [];

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status,
                    'primary_domain' => $tenant->domains->first()?->hostname,
                    'gmv_total' => (float) ($metrics['gmv_total'] ?? 0),
                    'completed_orders_count' => (int) ($metrics['completed_orders_count'] ?? 0),
                    'products_count' => (int) ($metrics['products_count'] ?? 0),
                    'customers_count' => (int) ($metrics['customers_count'] ?? 0),
                ];
            })
            ->sortByDesc('gmv_total')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @param  array<int, array<string, mixed>>  $metricsMap
     * @return array<int, array<string, mixed>>
     */
    public function attentionMerchants(Collection $tenants, array $metricsMap, int $limit = 6): array
    {
        return $tenants
            ->map(function (Tenant $tenant) use ($metricsMap): array {
                $metrics = $metricsMap[$tenant->id] ?? [];
                $reasons = [];

                if ($tenant->status === 'draft') {
                    $reasons[] = 'Awaiting approval';
                }
                if ($tenant->status === 'suspended') {
                    $reasons[] = 'Suspended by owner';
                }
                if (($metrics['pending_custom_domains'] ?? 0) > 0) {
                    $reasons[] = 'Pending custom domain verification';
                }
                if (($metrics['has_past_due_subscription'] ?? false) === true) {
                    $reasons[] = 'Subscription past due';
                }
                if (($metrics['products_count'] ?? 0) === 0) {
                    $reasons[] = 'No products uploaded';
                }
                if (!in_array($tenant->onboarding_status, ['live', 'completed'], true)) {
                    $reasons[] = 'Onboarding incomplete';
                }

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status,
                    'primary_domain' => $tenant->domains->first()?->hostname,
                    'reasons' => $reasons,
                    'gmv_total' => (float) ($metrics['gmv_total'] ?? 0),
                    'products_count' => (int) ($metrics['products_count'] ?? 0),
                    'customers_count' => (int) ($metrics['customers_count'] ?? 0),
                ];
            })
            ->filter(fn (array $tenant) => $tenant['reasons'] !== [])
            ->sortByDesc(fn (array $tenant) => count($tenant['reasons']))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function latestAuditEvents(int $limit = 6): array
    {
        return PlatformAuditLog::query()
            ->with(['actor', 'tenant'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (PlatformAuditLog $log) => [
                'id' => $log->id,
                'action_code' => $log->action_code,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'actor_scope' => $log->actor_scope,
                'created_at' => $log->created_at,
                'tenant' => $log->tenant?->only(['id', 'name', 'slug', 'status']),
                'actor' => $log->actor?->only(['id', 'name', 'email']),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function merchantGrowthSeries(int $days = 14): array
    {
        $startDate = now()->copy()->subDays($days - 1)->startOfDay();
        $counts = Tenant::query()
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS aggregate_value')
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->groupBy('day')
            ->pluck('aggregate_value', 'day');

        return collect(CarbonPeriod::create($startDate, now()->startOfDay()))
            ->map(fn (Carbon $date) => [
                'date' => $date->toDateString(),
                'count' => (int) ($counts[$date->toDateString()] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function salesTrendSeries(int $days = 14): array
    {
        $startDate = now()->copy()->subDays($days - 1)->startOfDay();
        $rows = Order::withoutGlobalScopes()
            ->selectRaw(
                'DATE(order_datetime) AS day, COUNT(*) AS orders_count, SUM(CASE WHEN payment_status = ? THEN total ELSE 0 END) AS gmv_total',
                [PaymentStatus::PAID]
            )
            ->whereDate('order_datetime', '>=', $startDate->toDateString())
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return collect(CarbonPeriod::create($startDate, now()->startOfDay()))
            ->map(function (Carbon $date) use ($rows): array {
                $row = $rows->get($date->toDateString());

                return [
                    'date' => $date->toDateString(),
                    'orders_count' => (int) ($row?->orders_count ?? 0),
                    'gmv_total' => (float) ($row?->gmv_total ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function uniqueCustomerCount(): int
    {
        return Customer::withoutGlobalScopes()
            ->get(['id', 'legacy_user_id', 'email', 'phone', 'country_code'])
            ->unique(function (Customer $customer): string {
                if ($customer->legacy_user_id) {
                    return 'legacy:'.$customer->legacy_user_id;
                }

                if (filled($customer->email)) {
                    return 'email:'.strtolower(trim((string) $customer->email));
                }

                if (filled($customer->phone)) {
                    return 'phone:'.preg_replace('/\s+/', '', (string) $customer->country_code.$customer->phone);
                }

                return 'shadow:'.$customer->id;
            })
            ->count();
    }
}
