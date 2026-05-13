<?php

namespace App\Services;

use PragmaRX\Countries\Package\Countries;

class CountryMetadataService
{
    private static array $cache = [];

    public function byCountryCode(?string $countryCode): array
    {
        $normalizedCountryCode = strtoupper(trim((string)$countryCode));
        if ($normalizedCountryCode === '') {
            return ['currency_code' => null, 'currency_symbol' => null];
        }

        if (array_key_exists($normalizedCountryCode, self::$cache)) {
            return self::$cache[$normalizedCountryCode];
        }

        $country = Countries::where('cca2', $normalizedCountryCode)->first();
        if (!$country) {
            return self::$cache[$normalizedCountryCode] = ['currency_code' => null, 'currency_symbol' => null];
        }

        $countryArray  = $country->toArray();
        $currencyCodes = $countryArray['currencies'] ?? [];
        $currencyCode  = is_array($currencyCodes) && count($currencyCodes) > 0 ? (string)$currencyCodes[0] : null;
        $currencySymbol = null;

        if ($currencyCode) {
            $hydratedCountry = $country->hydrateCurrencies();
            $currencyCollection = $hydratedCountry->currencies[$currencyCode] ?? null;
            $currencyArray = is_object($currencyCollection) && method_exists($currencyCollection, 'toArray')
                ? $currencyCollection->toArray()
                : (is_array($currencyCollection) ? $currencyCollection : []);

            $currencySymbol = data_get($currencyArray, 'units.major.symbol');
        }

        return self::$cache[$normalizedCountryCode] = [
            'currency_code'   => $currencyCode,
            'currency_symbol' => $currencySymbol,
        ];
    }
}
