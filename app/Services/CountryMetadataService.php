<?php

namespace App\Services;

use PragmaRX\Countries\Package\Countries;

class CountryMetadataService
{
    private static array $cache = [];
    private static array $nameCache = [];
    private static array $callingCodeCache = [];
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

    public function byCountryName(?string $countryName): array
    {
        $normalizedCountryName = strtolower(trim((string)$countryName));
        if ($normalizedCountryName === '') {
            return ['currency_code' => null, 'currency_symbol' => null];
        }

        $this->warmCache();

        return self::$nameCache[$normalizedCountryName] ?? ['currency_code' => null, 'currency_symbol' => null];
    }

    public function countryCodeByCallingCode(?string $callingCode): ?string
    {
        $normalizedCallingCode = trim((string)$callingCode);
        if ($normalizedCallingCode === '') {
            return null;
        }

        $this->warmCache();

        return self::$callingCodeCache[$normalizedCallingCode] ?? null;
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

            foreach (array_filter([
                data_get($countryArray, 'name.common'),
                data_get($countryArray, 'name_en'),
                data_get($countryArray, 'admin'),
            ], static fn ($countryName): bool => is_scalar($countryName) && (string)$countryName !== '') as $countryName) {
                self::$nameCache[strtolower((string)$countryName)] = self::$cache[$countryCode];
            }

            foreach ((array)data_get($countryArray, 'calling_codes', []) as $callingCode) {
                if (filled($callingCode)) {
                    self::$callingCodeCache[(string)$callingCode] = $countryCode;
                }
            }
        }

        self::$cacheWarmed = true;
    }
}
