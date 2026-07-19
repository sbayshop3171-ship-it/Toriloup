<?php

namespace App\Services\Currency;

use App\Enums\CurrencyPosition;
use App\Models\Currency;
use App\Models\Tenant;
use Illuminate\Http\Request;

class CurrencyConversionService
{
    public function __construct(
        private readonly CurrencyCatalogService $currencyCatalogService,
        private readonly VisitorCurrencyResolver $visitorCurrencyResolver,
    ) {
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function priceForRequest(float $amount, Request $request, ?Tenant $tenant = null, array $settings = []): array
    {
        $display = $this->visitorCurrencyResolver->resolve($request, $tenant, $settings);
        $baseCode = $display['base_code'];
        $converted = $this->convert($amount, $baseCode, $display['code'], $tenant);

        return [
            'base_amount' => $this->round($amount, $baseCode, $tenant),
            'base_currency_code' => $baseCode,
            'display_amount' => $converted,
            'display_currency_code' => $display['code'],
            'display_currency_symbol' => $display['symbol'],
            'display_currency_minor_unit' => $display['minor_unit'],
            'display_exchange_rate' => $this->exchangeRateBetween($baseCode, $display['code'], $tenant),
            'display_rate_source' => $display['rate_source'],
            'display_rate_synced_at' => $display['rate_synced_at'],
            'currency_source' => $display['source'],
            'formatted' => $this->format($converted, $display['code'], $display['symbol'], $display['minor_unit']),
        ];
    }

    public function convert(float $amount, string $fromCode, string $toCode, ?Tenant $tenant = null): float
    {
        $rate = $this->exchangeRateBetween($fromCode, $toCode, $tenant);

        return $this->round($amount * $rate, $toCode, $tenant);
    }

    public function exchangeRateBetween(string $fromCode, string $toCode, ?Tenant $tenant = null): float
    {
        $from = $this->currencyCatalogService->findByCode($fromCode, $tenant);
        $to = $this->currencyCatalogService->findByCode($toCode, $tenant);

        $fromRate = max((float) ($from?->exchange_rate ?? 1), 0.00000001);
        $toRate = max((float) ($to?->exchange_rate ?? 1), 0.00000001);

        return round($toRate / $fromRate, 8);
    }

    public function round(float $amount, string $code, ?Tenant $tenant = null): float
    {
        $currency = $this->currencyCatalogService->findByCode($code, $tenant);
        $minorUnit = (int) ($currency?->minor_unit ?? 2);

        return (float) number_format($amount, $minorUnit, '.', '');
    }

    public function format(float $amount, string $code, ?string $symbol = null, ?int $minorUnit = null, int|string|null $position = null): string
    {
        $currency = $this->currencyCatalogService->findByCode($code);
        $symbol ??= $currency?->symbol ?: $code;
        $minorUnit ??= (int) ($currency?->minor_unit ?? 2);
        $formatted = number_format($amount, $minorUnit, '.', '');
        $position ??= env('CURRENCY_POSITION', CurrencyPosition::LEFT);

        return (int) $position === CurrencyPosition::LEFT
            ? $symbol.$formatted
            : $formatted.$symbol;
    }
}
