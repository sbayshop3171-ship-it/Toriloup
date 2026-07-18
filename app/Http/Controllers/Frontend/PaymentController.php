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
use App\Models\ThemeSetting;
use App\Services\PaymentManagerService;
use App\Services\Saas\TenantPaymentMethodCatalogService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Dipokhalder\Settings\Facades\Settings;

class PaymentController extends Controller
{
    private PaymentManagerService $paymentManagerService;

    public function __construct(
        PaymentManagerService $paymentManagerService,
        private readonly TenantPaymentMethodCatalogService $tenantPaymentMethodCatalogService,
    ) {
        $this->paymentManagerService = $paymentManagerService;
    }

    public function index(PaymentGateway $paymentGateway, Order $order): \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse
    {
        if (!$this->orderCanUseGateway($order, $paymentGateway->slug)) {
            return redirect('/checkout/payment')->with('error', trans('all.message.payment_gateway_disable'));
        }

        $credit          = false;
        $cashOnDelivery  = false;
        $paymentGateways = $this->paymentGatewaysForOrder($order);
        $company         = Settings::group('company')->all();
        $site            = Settings::group('site')->all();
        $logo            = ThemeSetting::where(['key' => 'theme_logo'])->first();
        $faviconLogo     = ThemeSetting::where(['key' => 'theme_favicon_logo'])->first();
        $currency        = Currency::findOrFail(Settings::group('site')->get('site_default_currency'));
        if ($order?->user?->balance >= $order->total && $this->orderAllowsGateway($order, 'credit')) {
            $credit = true;
        }

        if (($site['site_cash_on_delivery'] ?? Activity::DISABLE) == Activity::ENABLE && $this->orderAllowsGateway($order, 'cashondelivery')) {
            $cashOnDelivery = true;
        }

        if (blank($order->transaction) && $order->payment_status === PaymentStatus::UNPAID) {
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
                'paymentMethod'   => $paymentGateway
            ]);
        }
        return redirect()->route('home')->with('error', trans('all.message.payment_canceled'));
    }

    public function payment(Order $order, PaymentRequest $request)
    {
        if (!$this->orderCanUseGateway($order, $request->paymentMethod)) {
            return redirect()->route('payment.index', [
                'paymentGateway' => $order->paymentMethod?->slug ?? $request->paymentMethod,
                'order' => $order,
            ])->with('error', trans('all.message.payment_gateway_disable'));
        }

        if ($this->paymentManagerService->gateway($request->paymentMethod)->status()) {
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

    public function success(PaymentGateway $paymentGateway, Order $order, Request $request)
    {
        return $this->paymentManagerService->gateway($paymentGateway->slug)->success($order, $request);
    }

    public function fail(PaymentGateway $paymentGateway, Order $order, Request $request)
    {
        return $this->paymentManagerService->gateway($paymentGateway->slug)->fail($order, $request);
    }

    public function cancel(PaymentGateway $paymentGateway, Order $order, Request $request)
    {
        return $this->paymentManagerService->gateway($paymentGateway->slug)->cancel($order, $request);
    }

    public function successful(Order $order): \Illuminate\Foundation\Application|\Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        try {
            SendOrderMail::dispatch(['order_id' => $order->id, 'status' => OrderStatus::PENDING]);
            SendOrderSms::dispatch(['order_id' => $order->id, 'status' => OrderStatus::PENDING]);
            SendOrderPush::dispatch(['order_id' => $order->id, 'status' => OrderStatus::PENDING]);

            SendOrderGotMail::dispatch(['order_id' => $order->id]);
            SendOrderGotSms::dispatch(['order_id' => $order->id]);
            SendOrderGotPush::dispatch(['order_id' => $order->id]);
        } catch (\Exception $e) {
        }

        return redirect('/account/order-success/' . $order->id);
    }

    private function orderCanUseGateway(Order $order, string $gatewaySlug): bool
    {
        $order->loadMissing('paymentMethod');

        return $order->paymentMethod?->slug === $gatewaySlug
            && $this->orderAllowsGateway($order, $gatewaySlug);
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
}
