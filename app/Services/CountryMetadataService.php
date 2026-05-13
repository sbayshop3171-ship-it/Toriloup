<?php

namespace App\Services;

use PragmaRX\Countries\Package\Countries;

class CountryMetadataService
{
    private static array $cache = [];
    private static bool $cacheWarmed = false;

    public function byCountryCode(?string $countryCode): array
    {
        $normalizedCountryCode = strtoupper(trim((string)$countryCode));
        if ($normalizedCountryCode === '') {
            return ['currency_code' => null, 'currency_symbol' => null];
        }

        $this->warmCache();

        return self::$cache[$normalizedCountryCode] ?? ['currency_code' => null, 'currency_symbol' => null];
    }

    private function warmCache(): void
    {
        if (self::$cacheWarmed) {
            return;
        }

        $currencySymbols = [];
        foreach (Countries::currencies() as $currencyCode => $currency) {
            $currencyArray = is_object($currency) && method_exists($currency, 'toArray')
                ? $currency->toArray()
                : (is_array($currency) ? $currency : []);

            $currencySymbols[(string)$currencyCode] = data_get($currencyArray, 'units.major.symbol');
        }

        foreach (Countries::all() as $country) {
            $countryArray = is_object($country) && method_exists($country, 'toArray')
                ? $country->toArray()
                : (is_array($country) ? $country : []);

            $countryCode = strtoupper((string)data_get($countryArray, 'cca2'));
            if ($countryCode === '') {
                continue;
            }

            $currencyCodes = data_get($countryArray, 'currencies', []);
            if (is_array($currencyCodes)) {
                $currencyCode = (string)($currencyCodes[0] ?? '');
            } else {
                $currencyCode = is_string($currencyCodes) ? $currencyCodes : '';
            }

            self::$cache[$countryCode] = [
                'currency_code'   => $currencyCode !== '' ? $currencyCode : null,
                'currency_symbol' => $currencyCode !== '' ? ($currencySymbols[$currencyCode] ?? null) : null,
            ];
        }

        self::$cacheWarmed = true;
    }
}
