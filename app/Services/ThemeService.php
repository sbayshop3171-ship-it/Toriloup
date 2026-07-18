<?php

namespace App\Services;


use App\Http\Requests\ThemeRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use App\Services\Saas\TenantSettingsService;
use App\Services\Tenancy\TenantContext;
use Dipokhalder\EnvEditor\EnvEditor;
use Exception;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;

class ThemeService
{
    public EnvEditor $envService;

    public function __construct(
        EnvEditor $envEditor,
        private readonly TenantContext $tenantContext,
        private readonly TenantSettingsService $tenantSettingsService,
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
                return $this->tenantSettingsService->groupForTenant($tenant, 'theme');
            }

            return Settings::group('theme')->all();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(ThemeRequest $request)
    {

        try {
            if ($tenant = $this->merchantTenant()) {
                $data = [];

                foreach (['theme_logo', 'theme_favicon_logo', 'theme_footer_logo'] as $field) {
                    if ($request->hasFile($field)) {
                        $data[$field] = $request->file($field)->store("tenants/{$tenant->id}/theme", 'public');
                    }
                }

                return $this->tenantSettingsService->syncGroupForTenant(
                    $tenant,
                    'theme',
                    $data,
                    request()->user()
                );
            }

            Settings::group('theme')->set($request->validated());
            if ($request->theme_logo) {
                $setting = ThemeSetting::where('key', 'theme_logo')->first();
                $setting->clearMediaCollection('theme-logo');
                $setting->addMediaFromRequest('theme_logo')->toMediaCollection('theme-logo');
            }
            if ($request->theme_favicon_logo) {
                $setting = ThemeSetting::where('key', 'theme_favicon_logo')->first();
                $setting->clearMediaCollection('theme-favicon-logo');
                $setting->addMediaFromRequest('theme_favicon_logo')->toMediaCollection('theme-favicon-logo');
            }
            if ($request->theme_footer_logo) {
                $setting = ThemeSetting::where('key', 'theme_footer_logo')->first();
                $setting->clearMediaCollection('theme-footer-logo');
                $setting->addMediaFromRequest('theme_footer_logo')->toMediaCollection('theme-footer-logo');
            }
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
