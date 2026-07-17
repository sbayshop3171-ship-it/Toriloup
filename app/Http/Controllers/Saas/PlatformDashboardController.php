<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PlatformProvider;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\TenantSubscription;
use Illuminate\Http\JsonResponse;

class PlatformDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'summary' => [
                'tenants_total' => Tenant::query()->count(),
                'tenants_active' => Tenant::query()->where('status', 'active')->count(),
                'tenants_draft' => Tenant::query()->where('status', 'draft')->count(),
                'tenants_suspended' => Tenant::query()->where('status', 'suspended')->count(),
                'tenants_live' => Tenant::query()->where('onboarding_status', 'live')->count(),
                'tenants_onboarding' => Tenant::query()->whereNotIn('onboarding_status', ['live', 'completed'])->count(),
                'new_signups_today' => Tenant::query()->whereDate('created_at', today())->count(),
                'custom_domains_pending' => TenantDomain::query()->where('domain_type', 'custom')->where('verification_status', 'pending')->count(),
                'custom_domains_verified' => TenantDomain::query()->where('domain_type', 'custom')->where('verification_status', 'verified')->count(),
                'domain_issues' => TenantDomain::query()
                    ->where('domain_type', 'custom')
                    ->where(function ($query): void {
                        $query->whereIn('verification_status', ['failed', 'rejected'])
                            ->orWhereIn('ssl_status', ['failed', 'expired']);
                    })
                    ->count(),
                'provider_issues' => PlatformProvider::query()->where('status', false)->count(),
                'merchant_memberships_active' => TenantMember::query()->where('status', 'active')->count(),
                'subscriptions_active' => TenantSubscription::query()->where('status', 'active')->count(),
                'orders_today' => Order::query()->whereDate('order_datetime', today())->count(),
                'gmv_today' => (float) Order::query()
                    ->whereDate('order_datetime', today())
                    ->where('payment_status', PaymentStatus::PAID)
                    ->sum('total'),
                'support_alerts' => 0,
            ],
        ]);
    }
}
