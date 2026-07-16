<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
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
                'tenants_suspended' => Tenant::query()->where('status', 'suspended')->count(),
                'tenants_live' => Tenant::query()->where('onboarding_status', 'live')->count(),
                'custom_domains_pending' => TenantDomain::query()->where('domain_type', 'custom')->where('verification_status', 'pending')->count(),
                'custom_domains_verified' => TenantDomain::query()->where('domain_type', 'custom')->where('verification_status', 'verified')->count(),
                'merchant_memberships_active' => TenantMember::query()->where('status', 'active')->count(),
            ],
        ]);
    }
}
