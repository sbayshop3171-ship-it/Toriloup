<?php

namespace App\Services\Currency;

use App\Enums\Activity;
use App\Models\Address;
use App\Models\Currency;
use App\Models\Tenant;
use App\Services\CountryMetadataService;
use App\Services\IpLocationService;
use App\Services\Saas\TenantSettingsService;
use Illuminate\Http\Request;

class VisitorCurrencyResolver
{
    /** @var array<int, array<string, mixed>> */
    private array $tenantSettingsCache = [];

    public function __construct(
        private readonly CountryMetadataService $countryMetadataService,
        private readonly CurrencyCatalogService $currencyCatalogService,
        private readonly TenantSettingsService $tenantSettingsService,
        private readonly IpLocationService $ipLocationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function resolve(Request $request, ?Tenant $tenant = null, array $settings = []): array
    {
        $settings = $this->effectiveSettings($tenant, $settings);
        $candidates = [
            ['source' => 'manual', 'currency_code' => $this->manualCurrencyCode($request), 'country_code' => null],
        ];

        if ($this->autoVisitorCurrencyEnabled($settings)) {
            $candidates[] = ['source' => 'checkout_shipping', 'currency_code' => $this->checkoutShippingCurrencyCode($request), 'country_code' => null];
            $candidates[] = ['source' => 'country_header', 'currency_code' => null, 'country_code' => $this->headerCountryCode($request)];
            $candidates[] = ['source' => 'ip_location', 'currency_code' => null, 'country_code' => null, 'ip_lookup' => true];
            $candidates[] = ['source' => 'browser_locale', 'currency_code' => null, 'country_code' => $this->browserCountryCode($request)];
        }

        $candidates[] = ['source' => 'store_base', 'currency_code' => $this->baseCurrencyCode($tenant, $settings), 'country_code' => null];

        foreach ($candidates as $candidate) {
            $currencyCode = $candidate['currency_code'];
            $countryCode = $candidate['country_code'];

            if (blank($currencyCode) && blank($countryCode) && ($candidate['ip_lookup'] ?? false)) {
                $countryCode = $this->ipLocationCountryCode($request);
            }

            if (blank($currencyCode) && filled($countryCode)) {
                $currencyCode = $this->countryMetadataService->byCountryCode($countryCode)['currency_code'] ?? null;
            }

            if (blank($currencyCode)) {
                continue;
            }

            $currency = $this->currencyCatalogService->findByCode((string) $currencyCode, $tenant);

            if ($currency instanceof Currency) {
                return $this->context($currency, (string) $candidate['source'], $countryCode, $tenant, $settings);
            }
        }

        $fallback = $this->currencyCatalogService->findByCode('USD', $tenant);

        return $this->context($fallback, 'fallback', null, $tenant, $settings);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function baseCurrencyCode(?Tenant $tenant = null, array $settings = []): string
    {
        $settings = $this->effectiveSettings($tenant, $settings);

        if (filled($tenant?->primary_currency_code)) {
            return strtoupper((string) $tenant->primary_currency_code);
        }

        if (filled($settings['site_default_currency_code'] ?? null)) {
            return strtoupper((string) $settings['site_default_currency_code']);
        }

        if (filled($settings['site_default_currency'] ?? null)) {
            $currency = Currency::withoutGlobalScopes()->find((int) $settings['site_default_currency']);

            if ($currency instanceof Currency) {
                return strtoupper($currency->code);
            }
        }

        return strtoupper((string) config('currency.base_code', 'USD'));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function context(?Currency $currency, string $source, ?string $countryCode, ?Tenant $tenant, array $settings): array
    {
        $baseCode = $this->baseCurrencyCode($tenant, $settings);
        $currency ??= $this->currencyCatalogService->findByCode($baseCode, $tenant);

        return [
            'code' => strtoupper((string) ($currency?->code ?: $baseCode)),
            'symbol' => (string) ($currency?->symbol ?: ($settings['site_default_currency_symbol'] ?? '$')),
            'name' => (string) ($currency?->name ?: $baseCode),
            'minor_unit' => (int) ($currency?->minor_unit ?? ($settings['site_digit_after_decimal_point'] ?? 2)),
            'exchange_rate' => (float) ($currency?->exchange_rate ?? 1),
            'rate_source' => $currency?->rate_source,
            'rate_synced_at' => optional($currency?->rate_synced_at)->toDateTimeString(),
            'source' => $source,
            'country_code' => $countryCode,
            'base_code' => $baseCode,
        ];
    }

    private function manualCurrencyCode(Request $request): ?string
    {
        $currency = $request->header('X-Currency-Code')
            ?: $request->query('currency')
            ?: $request->cookie('toriloup_currency');

        return filled($currency) ? strtoupper(substr((string) $currency, 0, 10)) : null;
    }

    private function headerCountryCode(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Country-Code', 'X-App-Country'] as $header) {
            $country = strtoupper((string) $request->header($header));

            if (preg_match('/^[A-Z]{2}$/', $country)) {
                return $country;
            }
        }

        return null;
    }

    private function checkoutShippingCurrencyCode(Request $request): ?string
    {
        if (!filled($request->input('shipping_id'))) {
            return null;
        }

        $address = Address::query()->find((int) $request->input('shipping_id'));

        if (!$address instanceof Address || blank($address->country)) {
            return null;
        }

        return $this->countryMetadataService->byCountryName($address->country)['currency_code'] ?? null;
    }

    private function browserCountryCode(Request $request): ?string
    {
        $language = (string) $request->header('Accept-Language');

        if (preg_match('/[-_]([A-Z]{2})(?:[,;]|$)/i', $language, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    private function ipLocationCountryCode(Request $request): ?string
    {
        $attributeKey = 'currency.ip_location_country_code';

        if ($request->attributes->has($attributeKey)) {
            return $request->attributes->get($attributeKey);
        }

        $location = $this->ipLocationService->detect($request);
        $countryCode = strtoupper((string) ($location['country_code'] ?? ''));

        $countryCode = preg_match('/^[A-Z]{2}$/', $countryCode) ? $countryCode : null;
        $request->attributes->set($attributeKey, $countryCode);

        return $countryCode;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function autoVisitorCurrencyEnabled(array $settings): bool
    {
        if (!array_key_exists('site_auto_visitor_currency', $settings)) {
            return true;
        }

        return (int) $settings['site_auto_visitor_currency'] !== Activity::DISABLE;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function effectiveSettings(?Tenant $tenant, array $settings): array
    {
        if ($settings !== [] || !$tenant instanceof Tenant) {
            return $settings;
        }

        return $this->tenantSettingsCache[$tenant->id] ??= $this->tenantSettingsService->mergedForTenant($tenant);
    }
}
