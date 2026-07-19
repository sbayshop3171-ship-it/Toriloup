<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Order;
use App\Services\Currency\CurrencyConversionService;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Support\Facades\Log;

abstract class PaymentAbstract
{

    public object $gateway;
    public object $paymentGateway;
    public object $paymentGatewayOption;
    public PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    abstract public function status();

    abstract public function payment($order, $request);

    abstract public function success($order, $request);

    abstract public function fail($order, $request);

    abstract public function cancel($order, $request);

    protected function siteCurrencyCode(string $fallback = 'USD', ?Order $order = null): string
    {
        $orderCurrency = $order?->charge_currency_code ?: $order?->display_currency_code;

        if (filled($orderCurrency)) {
            return strtoupper((string) $orderCurrency);
        }

        $currencyId = Settings::group('site')->get('site_default_currency');

        if (filled($currencyId)) {
            $currency = Currency::query()->find((int) $currencyId)
                ?: Currency::withoutGlobalScopes()->find((int) $currencyId);

            if ($currency instanceof Currency && filled($currency->code)) {
                return strtoupper((string) $currency->code);
            }
        }

        $currencyCode = Settings::group('site')->get('site_default_currency_code');

        return strtoupper((string) (filled($currencyCode) ? $currencyCode : $fallback));
    }

    /**
     * @param array<int, string> $supportedCurrencies
     * @return array{amount: float, currency: string}
     */
    protected function gatewayPaymentAmount(Order $order, string $fallbackCurrency = 'USD', array $supportedCurrencies = []): array
    {
        $order->loadMissing('tenant');

        $fallbackCurrency = strtoupper(trim($fallbackCurrency)) ?: 'USD';
        $orderCurrency = strtoupper(trim((string) ($order->charge_currency_code ?: $order->display_currency_code)));
        $currency = $orderCurrency ?: $this->siteCurrencyCode($fallbackCurrency, $order);
        $supportedCurrencies = array_map(
            fn (string $currencyCode): string => strtoupper(trim($currencyCode)),
            $supportedCurrencies
        );

        if (!empty($supportedCurrencies) && !in_array($currency, $supportedCurrencies, true)) {
            $currency = $fallbackCurrency;
        }

        $amount = (float) $order->total;

        if (filled($orderCurrency) && $currency !== $orderCurrency) {
            try {
                $amount = app(CurrencyConversionService::class)->convert($amount, $orderCurrency, $currency, $order->tenant);
            } catch (\Throwable $throwable) {
                Log::warning('Gateway currency conversion failed; using original amount.', [
                    'order_id' => $order->id,
                    'from' => $orderCurrency,
                    'to' => $currency,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        return [
            'amount' => $amount,
            'currency' => $currency,
        ];
    }

    protected function gatewayDecimalAmount(float $amount, string $currencyCode, ?Order $order = null): string
    {
        $minorUnit = $this->currencyMinorUnit($currencyCode, $order);

        return number_format($amount, $minorUnit, '.', '');
    }

    protected function gatewayMinorAmount(float $amount, string $currencyCode, ?Order $order = null): int
    {
        $minorUnit = $this->currencyMinorUnit($currencyCode, $order);
        $factor = 10 ** max(0, min($minorUnit, 3));

        return (int) round($amount * $factor);
    }

    private function currencyMinorUnit(string $currencyCode, ?Order $order = null): int
    {
        $order?->loadMissing('tenant');
        $currency = Currency::query()->where('code', strtoupper($currencyCode))->first()
            ?: Currency::withoutGlobalScopes()
                ->when($order?->tenant, fn ($query) => $query->where('tenant_id', $order->tenant->id))
                ->where('code', strtoupper($currencyCode))
                ->first();

        return (int) ($currency?->minor_unit ?? 2);
    }
}
