<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\CountryMetadataService;

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
        return app(CountryMetadataService::class)->byCountryCode($countryCode);
    }
};
