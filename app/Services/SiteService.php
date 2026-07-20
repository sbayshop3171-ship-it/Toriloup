<?php

namespace App\Services;


use App\Enums\Activity;
use App\Http\Requests\SiteRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\Currency;
use App\Models\Tenant;
use App\Services\Currency\CurrencyCatalogService;
use App\Services\Currency\MerchantBaseCurrencyConversionService;
use App\Services\Saas\TenantSettingsService;
use App\Services\Tenancy\TenantContext;
use Dipokhalder\EnvEditor\EnvEditor;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;

class SiteService
{
    public EnvEditor $envService;

    public function __construct(
        EnvEditor $envEditor,
        private readonly TenantContext $tenantContext,
        private readonly TenantSettingsService $tenantSettingsService,
        private readonly CurrencyCatalogService $currencyCatalogService,
        private readonly MerchantBaseCurrencyConversionService $merchantBaseCurrencyConversionService,
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
                return $this->normalizeMerchantCurrencySettings(
                    $tenant,
                    $this->tenantSettingsService->groupForTenant($tenant, 'site')
                );
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

            if ($tenant = $this->merchantTenant()) {
                $data = $request->validated();
                $currentSettings = $this->tenantSettingsService->groupForTenant($tenant, 'site');

                return DB::transaction(function () use ($tenant, $currency, $data, $currentSettings, $request) {
                    $merchantSettings = [
                        'site_auto_visitor_currency' => $data['site_auto_visitor_currency'] ?? Activity::ENABLE,
                    ];

                    if ($currency instanceof Currency) {
                        $tenantCurrency = $this->currencyCatalogService->findByCode($currency->code, $tenant);

                        if ($tenantCurrency instanceof Currency) {
                            $oldCurrencyCode = $this->currentMerchantBaseCurrencyCode($tenant, $currentSettings);
                            $newCurrencyCode = strtoupper($tenantCurrency->code);

                            if ($oldCurrencyCode !== $newCurrencyCode) {
                                $this->merchantBaseCurrencyConversionService->convertCurrentAmounts(
                                    $tenant,
                                    $oldCurrencyCode,
                                    $newCurrencyCode,
                                    $request->user()
                                );
                            }

                            $merchantSettings['site_default_currency'] = $tenantCurrency->id;
                            $merchantSettings['site_default_currency_code'] = $newCurrencyCode;
                            $merchantSettings['site_default_currency_symbol'] = $tenantCurrency->symbol;

                            $tenant->forceFill([
                                'primary_currency_code' => $newCurrencyCode,
                            ])->save();
                        }
                    }

                    return $this->tenantSettingsService->syncGroupForTenant(
                        $tenant,
                        'site',
                        $merchantSettings,
                        $request->user()
                    );
                });
            }

            $app_debug = $this->envService->getValue('DEMO') ? Activity::DISABLE : $request->site_app_debug;

            $data = $request->validated();
            $data['site_default_currency_symbol'] = $currency?->symbol ?? (string) ($data['site_default_currency_symbol'] ?? '$');
            $data['site_default_currency_code'] = $currency?->code ?? config('currency.base_code', 'USD');
            $data['site_app_debug'] = $app_debug;
            $data['site_auto_visitor_currency'] = $data['site_auto_visitor_currency'] ?? Activity::ENABLE;

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

    /**
     * Keep the merchant currency form aligned with the tenant's actual store base currency.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizeMerchantCurrencySettings(Tenant $tenant, array $settings): array
    {
        $currencyCode = $this->currentMerchantBaseCurrencyCode($tenant, $settings);

        if ($currencyCode === '') {
            $currencyCode = strtoupper((string) config('currency.base_code', 'USD'));
        }

        $currency = $this->currencyCatalogService->findByCode($currencyCode, $tenant);

        if (!$currency instanceof Currency && filled($settings['site_default_currency'] ?? null)) {
            $storedCurrency = Currency::withoutGlobalScopes()->find((int) $settings['site_default_currency']);

            if ($storedCurrency instanceof Currency) {
                $currency = $this->currencyCatalogService->findByCode((string) $storedCurrency->code, $tenant)
                    ?: $storedCurrency;
            }
        }

        $settings['site_default_currency'] = $currency?->id ?? ($settings['site_default_currency'] ?? null);
        $settings['site_default_currency_code'] = strtoupper((string) ($currency?->code ?? $currencyCode));
        $settings['site_default_currency_symbol'] = $currency?->symbol ?? ($settings['site_default_currency_symbol'] ?? '$');
        $settings['site_auto_visitor_currency'] = $settings['site_auto_visitor_currency'] ?? Activity::ENABLE;

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function currentMerchantBaseCurrencyCode(Tenant $tenant, array $settings): string
    {
        return strtoupper(trim((string) (
            $tenant->primary_currency_code
            ?: ($settings['site_default_currency_code'] ?? '')
            ?: config('currency.base_code', 'USD')
        )));
    }
}
