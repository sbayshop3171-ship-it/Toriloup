<?php

namespace App\Http\Controllers\Frontend;


use App\Enums\Activity;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\SendOrderGotMail;
use App\Events\SendOrderGotPush;
use App\Events\SendOrderGotSms;
use App\Events\SendOrderMail;
use App\Events\SendOrderPush;
use App\Events\SendOrderSms;
use App\Http\Requests\PaymentRequest;
use App\Libraries\AppLibrary;
use App\Models\Currency;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\TenantPaymentMethod;
use App\Models\Transaction;
use App\Models\ThemeSetting;
use App\Services\PaymentAttemptService;
use App\Services\PaymentManagerService;
use App\Services\Saas\TenantPaymentMethodCatalogService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Dipokhalder\Settings\Facades\Settings;

class PaymentController extends Controller
{
    private PaymentManagerService $paymentManagerService;

    public function __construct(
        PaymentManagerService $paymentManagerService,
        private readonly TenantPaymentMethodCatalogService $tenantPaymentMethodCatalogService,
        private readonly TenantContext $tenantContext,
        private readonly PaymentAttemptService $paymentAttemptService,
    ) {
        $this->paymentManagerService = $paymentManagerService;
    }

    public function index(string $paymentGateway, int|string $order): \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse
    {
        $paymentGateway = $this->resolvePaymentGateway($paymentGateway);
        $order = $this->resolveOrder($order);

        if (!$paymentGateway instanceof PaymentGateway || !$order instanceof Order) {
            return redirect('/checkout/payment')->with('error', trans('all.message.something_wrong'));
        }

        if (!$this->orderCanStartGateway($order, $paymentGateway->slug)) {
            return redirect('/checkout/payment')->with('error', trans('all.message.payment_gateway_disable'));
        }

        $credit          = false;
        $cashOnDelivery  = false;
        $paymentGateways = $this->paymentGatewaysForOrder($order);
        $company         = Settings::group('company')->all();
        $site            = Settings::group('site')->all();
        $logo            = ThemeSetting::where(['key' => 'theme_logo'])->first();
        $faviconLogo     = ThemeSetting::where(['key' => 'theme_favicon_logo'])->first();
        $currency        = $this->paymentCurrency($site);
        if ($order?->user?->balance >= $order->total && $this->orderAllowsGateway($order, 'credit')) {
            $credit = true;
        }

        if (($site['site_cash_on_delivery'] ?? Activity::DISABLE) == Activity::ENABLE && $this->orderAllowsGateway($order, 'cashondelivery')) {
            $cashOnDelivery = true;
        }

        if (blank($order->transaction) && $order->payment_status === PaymentStatus::UNPAID) {
            $paymentAttempt = $this->paymentAttemptService->prepare($order, $paymentGateway->slug);

            return view('payment', [
                'company'         => $company,
                'logo'            => $logo,
                'currency'        => $currency,
                'faviconLogo'     => $faviconLogo,
                'paymentGateways' => $paymentGateways,
                'order'           => $order,
                'creditAmount'    => AppLibrary::currencyAmountFormat($order->user?->balance),
                'credit'          => $credit,
                'cashOnDelivery'  => $cashOnDelivery,
                'paymentAttempt'  => $paymentAttempt,
                'paymentMethod'   => $paymentGateway
            ]);
        }
        return redirect()->route('home')->with('error', trans('all.message.payment_canceled'));
    }

    public function payment(int|string $order, PaymentRequest $request)
    {
        $order = $this->resolveOrder($order);

        if (!$order instanceof Order) {
            return redirect('/checkout/payment')->with('error', trans('all.message.something_wrong'));
        }

        if (!$this->orderCanStartGateway($order, $request->paymentMethod)) {
            return redirect()->route('payment.index', [
                'paymentGateway' => $order->paymentMethod?->slug ?? $request->paymentMethod,
                'order' => $order,
            ])->with('error', trans('all.message.payment_gateway_disable'));
        }

        if ($this->paymentManagerService->gateway($request->paymentMethod)->status()) {
            $this->paymentAttemptService->start(
                $order,
                $request->paymentMethod,
                $request->input('paymentAttemptKey') ?: $request->header('Idempotency-Key')
            );

            $className = 'App\\Http\\PaymentGateways\\PaymentRequests\\' . ucfirst($request->paymentMethod);
            $gateway   = new $className;
            $request->validate($gateway->rules());
            return $this->paymentManagerService->gateway($request->paymentMethod)->payment($order, $request);
        } else {
            return redirect()->route('payment.index', ['paymentGateway' => $request->paymentMethod, 'order' => $order])->with(
                'error',
                trans('all.message.payment_gateway_disable')
            );
        }
    }

    public function success(string $paymentGateway, int|string $order, Request $request)
    {
        $paymentGateway = $this->resolvePaymentGateway($paymentGateway);
        $order = $this->resolveOrder($order);

        if (!$paymentGateway instanceof PaymentGateway || !$order instanceof Order || !$this->orderUsesGateway($order, $paymentGateway->slug)) {
            return redirect('/checkout/payment')->with('error', trans('all.message.payment_gateway_disable'));
        }

        return $this->paymentManagerService->gateway($paymentGateway->slug)->success($order, $request);
    }

    public function fail(string $paymentGateway, int|string $order, Request $request)
    {
        $paymentGateway = $this->resolvePaymentGateway($paymentGateway);
        $order = $this->resolveOrder($order);

        if (!$paymentGateway instanceof PaymentGateway || !$order instanceof Order || !$this->orderUsesGateway($order, $paymentGateway->slug)) {
            return redirect('/checkout/payment')->with('error', trans('all.message.payment_gateway_disable'));
        }

        $this->paymentAttemptService->markFailed($order, $paymentGateway->slug, 'Gateway returned failure.');

        return $this->paymentManagerService->gateway($paymentGateway->slug)->fail($order, $request);
    }

    public function cancel(string $paymentGateway, int|string $order, Request $request)
    {
        $paymentGateway = $this->resolvePaymentGateway($paymentGateway);
        $order = $this->resolveOrder($order);

        if (!$paymentGateway instanceof PaymentGateway || !$order instanceof Order || !$this->orderUsesGateway($order, $paymentGateway->slug)) {
            return redirect('/checkout/payment')->with('error', trans('all.message.payment_gateway_disable'));
        }

        $this->paymentAttemptService->markCanceled($order, $paymentGateway->slug, 'Customer or gateway canceled payment.');

        return $this->paymentManagerService->gateway($paymentGateway->slug)->cancel($order, $request);
    }

    public function successful(int|string $order): \Illuminate\Foundation\Application|\Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $order = $this->resolveOrder($order);

        if (!$order instanceof Order) {
            return redirect('/checkout/payment')->with('error', trans('all.message.something_wrong'));
        }

        if (!$this->hasPaymentTransaction($order)) {
            try {
                SendOrderMail::dispatch(['order_id' => $order->id, 'status' => OrderStatus::PENDING]);
                SendOrderSms::dispatch(['order_id' => $order->id, 'status' => OrderStatus::PENDING]);
                SendOrderPush::dispatch(['order_id' => $order->id, 'status' => OrderStatus::PENDING]);

                SendOrderGotMail::dispatch(['order_id' => $order->id]);
                SendOrderGotSms::dispatch(['order_id' => $order->id]);
                SendOrderGotPush::dispatch(['order_id' => $order->id]);
            } catch (\Exception $e) {
            }
        }

        return redirect('/account/order-success/' . $order->id);
    }

    private function resolvePaymentGateway(string $paymentGateway): ?PaymentGateway
    {
        return PaymentGateway::query()
            ->where('slug', strtolower(trim($paymentGateway)))
            ->first();
    }

    private function resolveOrder(int|string $order): ?Order
    {
        $orderId = (int) $order;

        if ($orderId <= 0) {
            return null;
        }

        $tenant = $this->tenantContext->current(request());
        $query = Order::withoutGlobalScope('tenant')
            ->with(['paymentMethod', 'tenant'])
            ->whereKey($orderId);

        if ($tenant !== null) {
            $query->where('tenant_id', $tenant->id);
        }

        return $query->first();
    }

    private function orderCanStartGateway(Order $order, string $gatewaySlug): bool
    {
        return $this->orderUsesGateway($order, $gatewaySlug)
            && $this->orderAllowsGateway($order, $gatewaySlug);
    }

    private function orderUsesGateway(Order $order, string $gatewaySlug): bool
    {
        $order->loadMissing('paymentMethod');

        return $order->paymentMethod?->slug === $gatewaySlug;
    }

    private function orderAllowsGateway(Order $order, string $gatewaySlug): bool
    {
        $order->loadMissing('tenant');

        if (!filled($order->tenant_id) || $order->tenant === null) {
            return PaymentGateway::query()
                ->where('slug', $gatewaySlug)
                ->where('status', Activity::ENABLE)
                ->exists();
        }

        return in_array(
            $gatewaySlug,
            $this->tenantPaymentMethodCatalogService->activeGatewaySlugsForTenant($order->tenant),
            true
        );
    }

    private function hasPaymentTransaction(Order $order): bool
    {
        return Transaction::withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->where('type', 'payment')
            ->where('sign', '+')
            ->exists();
    }

    private function paymentGatewaysForOrder(Order $order)
    {
        $order->loadMissing('tenant');

        if (!filled($order->tenant_id) || $order->tenant === null) {
            return PaymentGateway::with('gatewayOptions')
                ->where(['status' => Activity::ENABLE])
                ->get();
        }

        $activeMethods = $this->tenantPaymentMethodCatalogService->activeMethodsForTenant($order->tenant);
        $methodsBySlug = $activeMethods->keyBy(
            fn (TenantPaymentMethod $method): string => $this->tenantPaymentMethodCatalogService->gatewaySlugForProviderCode($method->provider_code)
        );

        return PaymentGateway::with('gatewayOptions')
            ->whereIn('slug', $methodsBySlug->keys()->all())
            ->where(['status' => Activity::ENABLE])
            ->get()
            ->each(function (PaymentGateway $gateway) use ($methodsBySlug): void {
                $method = $methodsBySlug->get($gateway->slug);

                if ($method instanceof TenantPaymentMethod) {
                    $gateway->setAttribute('name', $method->display_name ?: $gateway->name);
                }
            });
    }

    private function paymentCurrency(array $site): Currency
    {
        $currencyId = $site['site_default_currency'] ?? Settings::group('site')->get('site_default_currency');

        if (filled($currencyId)) {
            $currency = Currency::query()->find((int) $currencyId)
                ?: Currency::withoutGlobalScopes()->find((int) $currencyId);

            if ($currency instanceof Currency) {
                return $currency;
            }
        }

        $currencyCode = strtoupper((string) ($site['site_default_currency_code'] ?? config('currency.base_code', 'USD')));
        $currency = Currency::query()->where('code', $currencyCode)->first()
            ?: Currency::withoutGlobalScopes()->where('code', $currencyCode)->first();

        return $currency ?: new Currency([
            'name' => $currencyCode,
            'symbol' => (string) ($site['site_default_currency_symbol'] ?? config('currency.base_symbol', '$')),
            'code' => $currencyCode,
            'exchange_rate' => 1,
        ]);
    }
}
