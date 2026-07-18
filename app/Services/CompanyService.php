<?php

namespace App\Services;


use App\Http\Requests\CompanyRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\Tenant;
use App\Services\Saas\TenantSettingsService;
use App\Services\Tenancy\TenantContext;
use Dipokhalder\EnvEditor\EnvEditor;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;

class CompanyService
{

    public $envService;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantSettingsService $tenantSettingsService
    )
    {
        $this->envService = new EnvEditor();
    }

    /**
     * @throws Exception
     */
    public function list()
    {
        try {
            if ($tenant = $this->merchantTenant()) {
                return $this->tenantSettingsService->groupForTenant($tenant, 'company');
            }

            return Settings::group('company')->all();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(CompanyRequest $request)
    {
        try {
            if ($tenant = $this->merchantTenant()) {
                return $this->tenantSettingsService->syncGroupForTenant(
                    $tenant,
                    'company',
                    $request->validated(),
                    $request->user()
                );
            }

            Settings::group('company')->set($request->validated());
            $this->envService->addData(['APP_NAME' => $request->company_name]);
            Artisan::call('optimize:clear');
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
