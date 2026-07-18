<?php

namespace App\Services;


use App\Enums\Activity;
use App\Http\Requests\SiteRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\Currency;
use App\Models\Tenant;
use App\Services\Saas\TenantSettingsService;
use App\Services\Tenancy\TenantContext;
use Dipokhalder\EnvEditor\EnvEditor;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;

class SiteService
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
                return $this->tenantSettingsService->groupForTenant($tenant, 'site');
            }

            return Settings::group('site')->all();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(SiteRequest $request)
    {
        try {
            $currency = Currency::query()->find($request->site_default_currency)
                ?: Currency::withoutGlobalScopes()->find($request->site_default_currency);
            $app_debug = $this->envService->getValue('DEMO') ? Activity::DISABLE : $request->site_app_debug;

            $data = $request->validated();
            $data['site_default_currency_symbol'] = $currency?->symbol ?? (string) ($data['site_default_currency_symbol'] ?? '$');
            $data['site_app_debug'] = $app_debug;

            if ($tenant = $this->merchantTenant()) {
                return $this->tenantSettingsService->syncGroupForTenant(
                    $tenant,
                    'site',
                    $data,
                    request()->user()
                );
            }

            Settings::group('site')->set($data);

            $this->envService->addData([
                'APP_DEBUG'              => $app_debug == Activity::ENABLE ? 'true' : 'false',
                'TIMEZONE'               => $request->site_default_timezone,
                'CURRENCY'               => $currency?->code,
                'CURRENCY_SYMBOL'        => $currency?->symbol,
                'CURRENCY_POSITION'      => $request->site_currency_position,
                'CURRENCY_DECIMAL_POINT' => $request->site_digit_after_decimal_point,
                'DATE_FORMAT'            => $request->site_date_format,
                'TIME_FORMAT'            => $request->site_time_format,
                'NON_PURCHASE_QUANTITY'  => $request->site_non_purchase_product_maximum_quantity
            ]);

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
