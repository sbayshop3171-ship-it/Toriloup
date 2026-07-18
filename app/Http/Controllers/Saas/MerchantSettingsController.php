<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Http\Requests\ShippingSetupRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\ShippingSetupResource;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPaymentMethod;
use App\Services\Saas\PlatformAuditLogService;
use App\Services\Saas\TenantSettingsService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantSettingsController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantSettingsService $tenantSettingsService,
        private readonly PlatformAuditLogService $platformAuditLogService,
    ) {
    }

    public function company(): CompanyResource
    {
        $tenant = $this->currentTenant();

        return new CompanyResource($this->companySettings($tenant));
    }

    public function updateCompany(CompanyRequest $request): CompanyResource
    {
        $request->validate([
            'company_logo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $tenant = $this->currentTenant();
        $settings = $request->validated();
        $settings['company_website'] = $this->storefrontUrl($tenant) ?? $settings['company_website'] ?? null;
        $oldValues = $this->companySettings($tenant);

        if ($request->hasFile('company_logo_file')) {
            $logoPath = $request->file('company_logo_file')->store("tenants/{$tenant->id}/branding", 'public');
            $settings['company_logo'] = $logoPath;
        }

        $merged = $this->tenantSettingsService->syncForTenant($tenant, $settings, $request->user());

        $tenant->forceFill([
            'name' => $settings['company_name'] ?? $tenant->name,
            'contact_email' => $settings['company_email'] ?? $tenant->contact_email,
            'contact_phone' => $settings['company_phone'] ?? $tenant->contact_phone,
            'country_code' => $settings['company_country_code'] ?? $tenant->country_code,
        ])->save();

        $this->platformAuditLogService->log(
            'merchant.settings.company.updated',
            'tenant',
            $tenant->id,
            $this->onlyKeys($oldValues, array_keys($settings)),
            $this->onlyKeys($merged, array_keys($settings)),
            $request,
            $request->user(),
            $tenant,
            'merchant'
        );

        return new CompanyResource($merged);
    }

    /**
     * @return array<string, mixed>
     */
    private function companySettings(Tenant $tenant): array
    {
        $settings = $this->tenantSettingsService->mergedForTenant($tenant);
        $settings['company_website'] = $this->storefrontUrl($tenant) ?? $settings['company_website'] ?? null;

        return $settings;
    }

    private function storefrontUrl(Tenant $tenant): ?string
    {
        $domain = $tenant->domains()
            ->where('is_primary', true)
            ->orderByDesc('is_fallback')
            ->first();

        if ($domain === null) {
            $domain = $tenant->domains()
                ->where('is_fallback', true)
                ->first();
        }

        return $domain instanceof TenantDomain && filled($domain->hostname)
            ? 'https://'.$domain->hostname
            : null;
    }

    public function shipping(): ShippingSetupResource
    {
        $tenant = $this->currentTenant();

        return new ShippingSetupResource($this->tenantSettingsService->mergedForTenant($tenant));
    }

    public function updateShipping(ShippingSetupRequest $request): ShippingSetupResource
    {
        $tenant = $this->currentTenant();
        $settings = $request->validated();
        $oldValues = $this->tenantSettingsService->mergedForTenant($tenant);
        $merged = $this->tenantSettingsService->syncForTenant($tenant, $settings, $request->user());

        $this->platformAuditLogService->log(
            'merchant.settings.shipping.updated',
            'tenant',
            $tenant->id,
            $this->onlyKeys($oldValues, array_keys($settings)),
            $this->onlyKeys($merged, array_keys($settings)),
            $request,
            $request->user(),
            $tenant,
            'merchant'
        );

        return new ShippingSetupResource($merged);
    }

    public function paymentMethods(): JsonResponse
    {
        $tenant = $this->currentTenant();

        return response()->json([
            'status' => true,
            'data' => $this->serializePaymentMethods($tenant),
        ]);
    }

    public function updatePaymentMethods(Request $request): JsonResponse
    {
        $tenant = $this->currentTenant();
        $validated = $request->validate([
            'methods' => ['required', 'array', 'min:1'],
            'methods.*.id' => ['required', 'integer'],
            'methods.*.status' => ['required', 'boolean'],
            'methods.*.display_name' => ['nullable', 'string', 'max:190'],
            'methods.*.checkout_label' => ['nullable', 'string', 'max:255'],
            'methods.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $methods = TenantPaymentMethod::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('id');

        foreach ($validated['methods'] as $payload) {
            $method = $methods->get((int) $payload['id']);

            if ($method === null) {
                continue;
            }

            $oldValues = $method->only(['status', 'display_name', 'checkout_label', 'sort_order']);

            $method->forceFill([
                'status' => (bool) $payload['status'],
                'display_name' => $payload['display_name'] ?: $method->display_name,
                'checkout_label' => $payload['checkout_label'] ?: $method->checkout_label,
                'sort_order' => $payload['sort_order'] ?? $method->sort_order,
            ])->save();

            $this->platformAuditLogService->log(
                'merchant.settings.payment-method.updated',
                'tenant_payment_method',
                $method->id,
                $oldValues,
                $method->only(array_keys($oldValues)),
                $request,
                $request->user(),
                $tenant,
                'merchant'
            );
        }

        return response()->json([
            'status' => true,
            'data' => $this->serializePaymentMethods($tenant),
        ]);
    }

    private function currentTenant(): Tenant
    {
        return $this->tenantContext->current() ?? abort(404, 'Tenant not resolved.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializePaymentMethods(Tenant $tenant): array
    {
        return TenantPaymentMethod::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->orderBy('id')
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
                'managed_by' => $method->config_json['managed_by'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function onlyKeys(array $payload, array $keys): array
    {
        $filtered = [];

        foreach ($keys as $key) {
            $filtered[$key] = $payload[$key] ?? null;
        }

        return $filtered;
    }
}
