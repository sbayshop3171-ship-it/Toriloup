<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Countries\Package\Countries;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('countries')) {
            Schema::table('countries', function (Blueprint $table) {
                if (!Schema::hasColumn('countries', 'currency_code')) {
                    $table->string('currency_code', 3)->nullable()->after('code');
                }

                if (!Schema::hasColumn('countries', 'currency_symbol')) {
                    $table->string('currency_symbol', 12)->nullable()->after('currency_code');
                }
            });

            $countries = DB::table('countries')
                ->select(['id', 'code', 'currency_code', 'currency_symbol'])
                ->where(function ($query) {
                    $query->whereNull('currency_code')->orWhereNull('currency_symbol');
                })
                ->get();

            foreach ($countries as $country) {
                $currencyMetadata = $this->currencyMetadataByCountryCode($country->code);
                DB::table('countries')->where('id', $country->id)->update([
                    'currency_code'   => $currencyMetadata['currency_code'],
                    'currency_symbol' => $currencyMetadata['currency_symbol'],
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('countries')) {
            Schema::table('countries', function (Blueprint $table) {
                if (Schema::hasColumn('countries', 'currency_symbol')) {
                    $table->dropColumn('currency_symbol');
                }

                if (Schema::hasColumn('countries', 'currency_code')) {
                    $table->dropColumn('currency_code');
                }
            });
        }
    }

    private function currencyMetadataByCountryCode(?string $countryCode): array
    {
        $normalizedCountryCode = strtoupper(trim((string)$countryCode));
        if ($normalizedCountryCode === '') {
            return ['currency_code' => null, 'currency_symbol' => null];
        }

        $country = Countries::where('cca2', $normalizedCountryCode)->first();
        if (!$country) {
            return ['currency_code' => null, 'currency_symbol' => null];
        }

        $countryArray = $country->toArray();
        $currencyCodes = $countryArray['currencies'] ?? [];
        $currencyCode = is_array($currencyCodes) && count($currencyCodes) > 0 ? (string)$currencyCodes[0] : null;
        $currencySymbol = null;

        if ($currencyCode) {
            $hydratedCountry = $country->hydrateCurrencies();
            $currency = $hydratedCountry->currencies[$currencyCode] ?? null;
            $currencyArray = is_object($currency) && method_exists($currency, 'toArray')
                ? $currency->toArray()
                : (is_array($currency) ? $currency : []);
            $currencySymbol = data_get($currencyArray, 'units.major.symbol');
        }

        return [
            'currency_code'   => $currencyCode,
            'currency_symbol' => $currencySymbol,
        ];
    }
};
