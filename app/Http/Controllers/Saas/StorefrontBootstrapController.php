<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Resources\SettingResource;
use App\Models\TenantFeatureFlag;
use App\Models\TenantPaymentMethod;
use App\Services\Saas\TenantSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontBootstrapController extends Controller
{
    public function __construct(private readonly TenantSettingsService $tenantSettingsService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));
        $tenantDomain = $request->attributes->get(config('tenancy.tenant_domain_attribute', 'saas.tenant_domain'));

        $settings = (new SettingResource($this->tenantSettingsService->mergedForTenant($tenant)))->toArray($request);

        return response()->json([
            'status' => true,
            'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
            'tenant' => $tenant?->only(['id', 'uuid', 'name', 'slug', 'status', 'plan_code', 'onboarding_status']),
            'domain' => $tenantDomain?->only(['hostname', 'domain_type', 'is_primary', 'is_fallback', 'verification_status']),
            'features' => $tenant ? TenantFeatureFlag::query()
                ->where('tenant_id', $tenant->id)
                ->get(['feature_code', 'status', 'source'])
                ->toArray() : [],
            'payment_methods' => $tenant ? TenantPaymentMethod::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', true)
                ->orderBy('sort_order')
                ->get(['provider_code', 'display_name', 'checkout_label', 'fee_type', 'fee_value'])
                ->toArray() : [],
            'data' => $settings,
        ]);
    }
}
