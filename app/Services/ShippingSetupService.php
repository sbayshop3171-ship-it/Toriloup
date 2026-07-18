<?php

namespace App\Services;


use App\Http\Requests\ShippingSetupRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\Tenant;
use App\Services\Saas\TenantSettingsService;
use App\Services\Tenancy\TenantContext;
use Dipokhalder\EnvEditor\EnvEditor;
use Exception;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;

class ShippingSetupService
{
    public $envService;

    public function __construct(
        EnvEditor $envEditor,
        private readonly TenantContext $tenantContext,
        private readonly TenantSettingsService $tenantSettingsService
    )
    {
        $this->envService = $envEditor;
    }

    /**
     * @throws Exception
     */
    public function list()
    {
        try {
            if ($tenant = $this->merchantTenant()) {
                return $this->tenantSettingsService->groupForTenant($tenant, 'shipping_setup');
            }

            return Settings::group('shipping_setup')->all();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(ShippingSetupRequest $request)
    {
        try {
            if ($tenant = $this->merchantTenant()) {
                return $this->tenantSettingsService->syncGroupForTenant(
                    $tenant,
                    'shipping_setup',
                    $request->validated(),
                    $request->user()
                );
            }

            Settings::group('shipping_setup')->set($request->validated());
            return $this->list();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    private function merchantTenant(): ?Tenant
    {
        if (app()->bound('saas.currentSurface') && app('saas.currentSurface') === 'merchant') {
            return $this->tenantContext->current();
        }

        return null;
    }
}
