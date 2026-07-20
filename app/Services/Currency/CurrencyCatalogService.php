<?php

namespace App\Services\Currency;

use App\Enums\Ask;
use App\Models\Currency;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Countries\Package\Countries;

class CurrencyCatalogService
{
    private static bool $globalSeeded = false;
    private static array $tenantEnsured = [];

    public function __construct(private readonly CurrencyRateProviderService $rateProvider)
    {
    }

    public function seedGlobalCurrencies(bool $force = false): int
    {
        if (!$force && self::$globalSeeded && Currency::withoutGlobalScopes()->whereNull('tenant_id')->exists()) {
            return 0;
        }

        $count = 0;

        foreach ($this->metadataFromCountries() as $metadata) {
            $currency = Currency::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->where('code', $metadata['code'])
                ->first();

            if (!$currency instanceof Currency) {
                $currency = new Currency(['tenant_id' => null, 'code' => $metadata['code']]);
            }

            $currency->fill($this->filterCurrencyColumns(array_merge($metadata, [
                'is_cryptocurrency' => Ask::NO,
                'exchange_rate' => $this->rateForCode($metadata['code']),
                'is_auto_managed' => true,
                'is_enabled' => true,
                'rate_source' => 'seed',
            ])));
            $currency->save();
            $count++;
        }

        self::$globalSeeded = true;

        return $count;
    }

    public function ensureTenantCurrencies(Tenant $tenant): int
    {
        if (
            isset(self::$tenantEnsured[$tenant->id])
            && Currency::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()
        ) {
            return 0;
        }

        $this->seedGlobalCurrencies();
        $created = 0;

        Currency::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->orderBy('code')
            ->get()
            ->each(function (Currency $globalCurrency) use ($tenant, &$created): void {
                $tenantCurrency = Currency::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('code', $globalCurrency->code)
                    ->first();

                $attributes = $this->filterCurrencyColumns([
                    'tenant_id' => $tenant->id,
                    'name' => $globalCurrency->name,
                    'symbol' => $globalCurrency->symbol,
                    'code' => $globalCurrency->code,
                    'minor_unit' => $globalCurrency->minor_unit ?? 2,
                    'is_cryptocurrency' => $globalCurrency->is_cryptocurrency ?? Ask::NO,
                    'exchange_rate' => $globalCurrency->exchange_rate ?? 1,
                    'is_auto_managed' => true,
                    'is_enabled' => $globalCurrency->is_enabled ?? true,
                    'rate_source' => $globalCurrency->rate_source,
                    'rate_synced_at' => $globalCurrency->rate_synced_at,
                    'rate_metadata_json' => $globalCurrency->rate_metadata_json,
                ]);

                if (!$tenantCurrency instanceof Currency) {
                    Currency::withoutGlobalScopes()->create($attributes);
                    $created++;

                    return;
                }

                if ((bool) ($tenantCurrency->is_auto_managed ?? false)) {
                    $tenantCurrency->fill($attributes)->save();
                }
            });

        self::$tenantEnsured[$tenant->id] = true;

        return $created;
    }

    /**
     * @return array{seeded: int, updated: int, source: string, failed: bool, message: string|null}
     */
    public function syncRates(?Tenant $tenant = null): array
    {
        $seeded = $this->seedGlobalCurrencies(true);
        $baseCode = strtoupper((string) config('currency.base_code', 'USD'));
        $failed = false;
        $message = null;

        try {
            $payload = $this->rateProvider->latest($baseCode);
        } catch (\Throwable $throwable) {
            $failed = true;
            $message = $throwable->getMessage();
            $payload = [
                'base' => $baseCode,
                'rates' => config('currency.fallback_rates', ['USD' => 1]),
                'source' => 'fallback',
                'synced_at' => now()->toDateTimeString(),
                'metadata' => ['error' => $message],
            ];
            Log::warning('Currency rate sync using fallback rates.', ['message' => $message]);
        }

        $updated = $this->applyRates($payload['rates'], $payload['source'], $payload['synced_at'], $payload['metadata']);
        self::$tenantEnsured = [];

        if ($tenant instanceof Tenant) {
            $this->ensureTenantCurrencies($tenant);
        } else {
            Tenant::query()->select(['id'])->chunkById(50, function ($tenants): void {
                foreach ($tenants as $tenant) {
                    $this->ensureTenantCurrencies($tenant);
                }
            });
        }

        return [
            'seeded' => $seeded,
            'updated' => $updated,
            'source' => $payload['source'],
            'failed' => $failed,
            'message' => $message,
        ];
    }

    /**
     * @return Collection<int, Currency>
     */
    public function availableCurrencies(?Tenant $tenant = null): Collection
    {
        if ($tenant instanceof Tenant) {
            $this->ensureTenantCurrencies($tenant);
        } else {
            $this->seedGlobalCurrencies();
        }

        return Currency::withoutGlobalScopes()
            ->when($tenant instanceof Tenant, fn ($query) => $query->where('tenant_id', $tenant->id), fn ($query) => $query->whereNull('tenant_id'))
            ->when($this->hasColumn('is_enabled'), fn ($query) => $query->where('is_enabled', true))
            ->orderBy('code')
            ->get();
    }

    public function findByCode(string $code, ?Tenant $tenant = null): ?Currency
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return null;
        }

        if ($tenant instanceof Tenant) {
            $this->ensureTenantCurrencies($tenant);

            $currency = Currency::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('code', $code)
                ->first();

            if ($currency instanceof Currency) {
                return $currency;
            }
        }

        $this->seedGlobalCurrencies();

        return Currency::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('code', $code)
            ->first();
    }

    public function currencyIdForCode(string $code, ?Tenant $tenant = null): ?int
    {
        return $this->findByCode($code, $tenant)?->id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function serializeOptions(?Tenant $tenant = null): array
    {
        return $this->availableCurrencies($tenant)
            ->map(fn (Currency $currency): array => [
                'id' => $currency->id,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'code' => $currency->code,
                'minor_unit' => (int) ($currency->minor_unit ?? 2),
                'exchange_rate' => (float) ($currency->exchange_rate ?? 1),
                'rate_source' => $currency->rate_source,
                'rate_synced_at' => optional($currency->rate_synced_at)->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, float>  $rates
     */
    private function applyRates(array $rates, string $source, string $syncedAt, array $metadata): int
    {
        $updated = 0;
        $rateColumns = $this->filterCurrencyColumns([
            'rate_source' => $source,
            'rate_synced_at' => $syncedAt,
            'rate_metadata_json' => json_encode($metadata),
        ]);

        foreach ($rates as $code => $rate) {
            if (!is_numeric($rate)) {
                continue;
            }

            $code = strtoupper((string) $code);
            $attributes = array_merge(['exchange_rate' => (float) $rate], $rateColumns);

            $affected = Currency::withoutGlobalScopes()
                ->where('code', $code)
                ->update($attributes);

            $updated += $affected;
        }

        return $updated;
    }

    /**
     * @return array<int, array{name: string, symbol: string, code: string, minor_unit: int}>
     */
    private function metadataFromCountries(): array
    {
        $currencies = [];

        foreach (Countries::currencies() as $code => $currency) {
            $currencyArray = is_object($currency) && method_exists($currency, 'toArray')
                ? $currency->toArray()
                : (is_array($currency) ? $currency : []);
            $code = strtoupper((string) data_get($currencyArray, 'iso.code', $code));

            if ($code === '') {
                continue;
            }

            $currencies[$code] = [
                'name' => (string) (data_get($currencyArray, 'name') ?: $code),
                'symbol' => (string) (data_get($currencyArray, 'units.major.symbol') ?: $code),
                'code' => $code,
                'minor_unit' => in_array($code, config('currency.zero_decimal', []), true) ? 0 : 2,
            ];
        }

        foreach (config('currency.fallback_rates', []) as $code => $rate) {
            $code = strtoupper((string) $code);
            $currencies[$code] ??= [
                'name' => $code,
                'symbol' => $code,
                'code' => $code,
                'minor_unit' => in_array($code, config('currency.zero_decimal', []), true) ? 0 : 2,
            ];
        }

        ksort($currencies);

        return array_values($currencies);
    }

    private function rateForCode(string $code): float
    {
        return (float) (config('currency.fallback_rates.'.strtoupper($code)) ?? ($code === strtoupper((string) config('currency.base_code', 'USD')) ? 1 : 1));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filterCurrencyColumns(array $attributes): array
    {
        return array_filter(
            $attributes,
            fn (string $column): bool => in_array($column, ['tenant_id', 'name', 'symbol', 'code', 'is_cryptocurrency', 'exchange_rate'], true)
                || $this->hasColumn($column),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function hasColumn(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::hasTable('currencies') ? Schema::getColumnListing('currencies') : [];
        }

        return in_array($column, $columns, true);
    }
}
