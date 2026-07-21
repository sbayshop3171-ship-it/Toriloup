<?php

namespace App\Services\Currency;

use App\Enums\CurrencyPosition;
use App\Models\Currency;
use App\Models\Tenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
    public function priceForRequest(float|int|string $amount, Request $request, ?Tenant $tenant = null, array $settings = []): array
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

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function basePriceForRequest(float|int|string $amount, Request $request, ?Tenant $tenant = null, array $settings = []): array
    {
        $baseCode = $this->visitorCurrencyResolver->baseCurrencyCode($tenant, $settings);
        $currency = $this->currencyCatalogService->findByCode($baseCode, $tenant);
        $minorUnit = (int) ($currency?->minor_unit ?? ($settings['site_digit_after_decimal_point'] ?? 2));
        $symbol = (string) ($currency?->symbol ?: ($settings['site_default_currency_symbol'] ?? $baseCode));
        $rounded = $this->round($amount, $baseCode, $tenant);

        return [
            'base_amount' => $rounded,
            'base_currency_code' => $baseCode,
            'base_currency_symbol' => $symbol,
            'base_currency_minor_unit' => $minorUnit,
            'formatted' => $this->format(
                $rounded,
                $baseCode,
                $symbol,
                $minorUnit,
                $settings['site_currency_position'] ?? null
            ),
        ];
    }

    public function convert(float|int|string $amount, string $fromCode, string $toCode, ?Tenant $tenant = null): float
    {
        return (float) $this->convertToString($amount, $fromCode, $toCode, $tenant);
    }

    public function convertToString(float|int|string $amount, string $fromCode, string $toCode, ?Tenant $tenant = null): string
    {
        $rate = $this->exchangeRateDecimalBetween($fromCode, $toCode, $tenant);
        $minorUnit = $this->minorUnit($toCode, $tenant);

        return (string) BigDecimal::of($this->decimalInput($amount))
            ->multipliedBy($rate)
            ->toScale($minorUnit, RoundingMode::HALF_UP);
    }

    public function exchangeRateBetween(string $fromCode, string $toCode, ?Tenant $tenant = null): float
    {
        return (float) (string) $this->exchangeRateDecimalBetween($fromCode, $toCode, $tenant)
            ->toScale(8, RoundingMode::HALF_UP);
    }

    public function exchangeRateDecimalBetween(string $fromCode, string $toCode, ?Tenant $tenant = null): BigDecimal
    {
        $from = $this->currencyCatalogService->findByCode($fromCode, $tenant);
        $to = $this->currencyCatalogService->findByCode($toCode, $tenant);

        $fromRate = $this->positiveRate($from?->exchange_rate ?? 1);
        $toRate = $this->positiveRate($to?->exchange_rate ?? 1);

        return $toRate->dividedBy($fromRate, 12, RoundingMode::HALF_UP);
    }

    public function round(float|int|string $amount, string $code, ?Tenant $tenant = null): float
    {
        return (float) (string) BigDecimal::of($this->decimalInput($amount))
            ->toScale($this->minorUnit($code, $tenant), RoundingMode::HALF_UP);
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

    private function minorUnit(string $code, ?Tenant $tenant = null): int
    {
        $currency = $this->currencyCatalogService->findByCode($code, $tenant);

        return (int) ($currency?->minor_unit ?? 2);
    }

    private function positiveRate(mixed $rate): BigDecimal
    {
        $decimal = BigDecimal::of($this->decimalInput($rate));

        return $decimal->isLessThanOrEqualTo(BigDecimal::zero())
            ? BigDecimal::of('0.00000001')
            : $decimal;
    }

    private function decimalInput(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.') ?: '0';
        }

        $value = trim(str_replace(',', '', (string) $value));

        return $value === '' ? '0' : $value;
    }
}
