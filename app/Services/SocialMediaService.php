<?php

namespace App\Services;

use App\Http\Requests\SocialMediaRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\Tenant;
use App\Services\Saas\TenantSettingsService;
use App\Services\Tenancy\TenantContext;
use Exception;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;

class SocialMediaService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantSettingsService $tenantSettingsService,
    ) {
    }

    /**
     * @throws Exception
     */
    public function list()
    {
        try {
            if ($tenant = $this->merchantTenant()) {
                return $this->tenantSettingsService->groupForTenant($tenant, 'social_media');
            }

            return Settings::group('social_media')->all();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @param SocialMediaRequest $request
     * @return
     * @throws Exception
     */
    public function update(SocialMediaRequest $request)
    {
        try {
            if ($tenant = $this->merchantTenant()) {
                return $this->tenantSettingsService->syncGroupForTenant(
                    $tenant,
                    'social_media',
                    $request->validated(),
                    request()->user()
                );
            }

            Settings::group('social_media')->set($request->validated());
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
