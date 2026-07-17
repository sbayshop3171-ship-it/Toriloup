<?php

namespace App\Http\Controllers\Saas;

use App\Enums\PaymentStatus;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Libraries\AppLibrary;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPaymentMethod;
use App\Services\Saas\TenantSettingsService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantDashboardController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantSettingsService $tenantSettingsService,
    ) {
    }

    public function setup(Request $request): JsonResponse
    {
        $tenant = $this->currentTenant();
        $settings = $this->tenantSettingsService->mergedForTenant($tenant);
        $fallbackDomain = $this->fallbackDomain($tenant);
        $primaryDomain = $this->primaryDomain($tenant) ?? $fallbackDomain;
        $totalSales = $this->totalSales();

        $metrics = [
            'total_sales' => AppLibrary::currencyAmountFormat($totalSales),
            'total_sales_raw' => $totalSales,
            'total_orders' => Order::query()->count(),
            'total_products' => Product::query()->count(),
            'total_customers' => Customer::query()->count(),
            'low_stock_alerts' => $this->lowStockAlerts(),
            'recent_orders' => $this->recentOrders(),
            'fallback_domain' => $this->serializeDomain($fallbackDomain),
            'primary_domain' => $this->serializeDomain($primaryDomain),
            'custom_domain_count' => $tenant->domains()->where('domain_type', 'custom')->count(),
            'storefront_url' => $primaryDomain?->hostname ? 'https://'.$primaryDomain->hostname : null,
        ];

        $checklist = $this->checklist($tenant, $settings, $fallbackDomain);
        $completed = collect($checklist)->where('completed', true)->count();
        $total = count($checklist);

        return response()->json([
            'status' => true,
            'data' => [
                'tenant' => $tenant->only(['id', 'uuid', 'name', 'slug', 'status', 'plan_code', 'onboarding_status']),
                'metrics' => $metrics,
                'checklist' => $checklist,
                'progress' => [
                    'completed' => $completed,
                    'total' => $total,
                    'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
                ],
            ],
        ]);
    }

    private function currentTenant(): Tenant
    {
        return $this->tenantContext->current() ?? abort(404, 'Tenant not resolved.');
    }

    private function totalSales(): float
    {
        return (float) Order::query()
            ->where('payment_status', PaymentStatus::PAID)
            ->sum('total');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return Order::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'order_serial_no', 'total', 'status', 'payment_status', 'order_datetime'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_serial_no' => $order->order_serial_no,
                'total' => AppLibrary::currencyAmountFormat((float) $order->total),
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'order_datetime' => $order->order_datetime?->toDateTimeString(),
            ])
            ->all();
    }

    private function lowStockAlerts(): int
    {
        $stockRows = Stock::query()
            ->where('status', Status::ACTIVE)
            ->select('product_id')
            ->selectRaw('SUM(quantity) as stock_quantity')
            ->groupBy('product_id')
            ->get();

        if ($stockRows->isEmpty()) {
            return 0;
        }

        $warnings = Product::query()
            ->whereIn('id', $stockRows->pluck('product_id')->filter()->all())
            ->pluck('low_stock_quantity_warning', 'id');

        return $stockRows
            ->filter(function ($row) use ($warnings) {
                $warning = (int) ($warnings[$row->product_id] ?? 0);

                return $warning > 0 && (int) $row->stock_quantity <= $warning;
            })
            ->count();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function checklist(Tenant $tenant, array $settings, ?TenantDomain $fallbackDomain): array
    {
        return [
            [
                'key' => 'general_settings',
                'title' => 'General Settings',
                'description' => 'Add store name, logo, contact, and address.',
                'route_name' => 'admin.settings.company',
                'completed' => $this->hasAllSettings($settings, [
                    'company_name',
                    'company_email',
                    'company_phone',
                    'company_address',
                    'company_logo',
                ]),
            ],
            [
                'key' => 'business_localization',
                'title' => 'Business & Localization',
                'description' => 'Confirm currency, timezone, language, and country.',
                'route_name' => 'admin.settings.company',
                'completed' => filled($tenant->primary_currency_code)
                    && filled($tenant->timezone)
                    && filled($tenant->primary_locale)
                    && filled($tenant->country_code ?? $settings['company_country_code'] ?? null),
            ],
            [
                'key' => 'first_product',
                'title' => 'Add Your First Product',
                'description' => 'Upload product details, images, pricing, and stock.',
                'route_name' => 'admin.products.list',
                'completed' => Product::query()->exists(),
            ],
            [
                'key' => 'shipping_delivery',
                'title' => 'Shipping & Delivery Setup',
                'description' => 'Set delivery method and store-level delivery charges.',
                'route_name' => 'admin.settings.shippingSetup',
                'completed' => filled($settings['shipping_setup_method'] ?? null)
                    && (
                        (float) ($settings['shipping_setup_flat_rate_wise_cost'] ?? 0) > 0
                        || (float) ($settings['shipping_setup_area_wise_default_cost'] ?? 0) > 0
                    ),
            ],
            [
                'key' => 'payment_methods',
                'title' => 'Payment Method Setup',
                'description' => 'Enable COD or owner-approved online payment methods.',
                'route_name' => 'admin.settings.paymentGateway',
                'completed' => TenantPaymentMethod::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', true)
                    ->exists(),
            ],
            [
                'key' => 'launch_domain',
                'title' => 'Launch & Domain Verification',
                'description' => 'Keep fallback subdomain active and connect custom domain when ready.',
                'route_name' => 'admin.settings.domains',
                'completed' => $fallbackDomain !== null && $fallbackDomain->verification_status === 'verified',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $keys
     */
    private function hasAllSettings(array $settings, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!filled($settings[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function fallbackDomain(Tenant $tenant): ?TenantDomain
    {
        return $tenant->domains()
            ->where('is_fallback', true)
            ->orderByDesc('is_primary')
            ->first();
    }

    private function primaryDomain(Tenant $tenant): ?TenantDomain
    {
        return $tenant->domains()
            ->where('is_primary', true)
            ->orderByDesc('is_fallback')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeDomain(?TenantDomain $domain): ?array
    {
        if ($domain === null) {
            return null;
        }

        return [
            'id' => $domain->id,
            'hostname' => $domain->hostname,
            'domain_type' => $domain->domain_type,
            'is_primary' => $domain->is_primary,
            'is_fallback' => $domain->is_fallback,
            'ssl_status' => $domain->ssl_status,
            'verification_status' => $domain->verification_status,
        ];
    }
}
