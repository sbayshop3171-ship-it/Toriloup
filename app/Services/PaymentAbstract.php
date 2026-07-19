<?php

namespace App\Services;

use App\Models\Currency;
use Dipokhalder\Settings\Facades\Settings;

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

    protected function siteCurrencyCode(string $fallback = 'USD'): string
    {
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
}
