<?php

namespace App\Http\PaymentGateways\Gateways;

use Exception;
use App\Enums\Ask;
use App\Enums\Status;
use App\Models\Order;
use App\Models\Stock;
use App\Enums\Activity;
use App\Models\PaymentGateway;
use App\Services\PaymentService;
use App\Services\PaymentAbstract;
use App\Services\PaymentAttemptService;
use App\Services\Saas\TenantPaymentMethodCatalogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;
use App\Models\CapturePaymentNotification;

class Cashondelivery extends PaymentAbstract
{
    public bool $response = false;

    public function __construct()
    {
        $paymentService = new PaymentService();
        parent::__construct($paymentService);
    }

    public function payment($order, $request): \Illuminate\Http\RedirectResponse
    {
        try {
            if ($this->canUseCashOnDelivery($order)) {
                $capturePaymentNotification = DB::table('capture_payment_notifications')->where([
                    ['order_id', $order->id]
                ]);
                $capturePaymentNotification?->delete();
                $token = rand(111111111, 999999999);
                CapturePaymentNotification::create([
                    'order_id'   => $order->id,
                    'token'      => $token,
                    'created_at' => now()
                ]);

                if ($token) {
                    return redirect()->away(route('payment.success', ['paymentGateway' => 'cashondelivery', 'order' => $order, 'token' => $token]));
                } else {
                    return redirect()->route('payment.index', ['order' => $order, 'paymentGateway' => 'cashondelivery'])->with('error', trans('all.message.something_wrong'));
                }
            } else {
                return redirect()->route('payment.index', ['order' => $order, 'paymentGateway' => 'cashondelivery'])->with('error', trans('all.message.something_wrong'));
            }
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return redirect()->route('payment.index', ['order' => $order, 'paymentGateway' => 'cashondelivery'])->with(
                'error',
                $e->getMessage()
            );
        }
    }

    public function status(): bool
    {
        $paymentGateways = PaymentGateway::where(['slug' => 'cashondelivery', 'status' => Activity::ENABLE])->first();
        if ($paymentGateways) {
            return true;
        }
        return false;
    }

    private function canUseCashOnDelivery($order): bool
    {
        if (filled($order?->tenant_id) && $order?->tenant) {
            return in_array(
                'cashondelivery',
                app(TenantPaymentMethodCatalogService::class)->activeGatewaySlugsForTenant($order->tenant),
                true
            );
        }

        $site = Settings::group('site')->all();

        return ($site['site_cash_on_delivery'] ?? Activity::DISABLE) == Activity::ENABLE
            && $this->status();
    }

    public function success($order, $request): \Illuminate\Http\RedirectResponse
    {
        try {
            DB::transaction(function () use ($order, $request) {
                if ($request->token) {
                    $paymentAttemptService = app(PaymentAttemptService::class);
                    $capturePaymentNotification = DB::table('capture_payment_notifications')->where([
                        ['token', $request->token]
                    ]);
                    $token                      = $capturePaymentNotification->first();

                    if (!blank($token) && $order->id == $token->order_id) {
                        $order->active = Ask::YES;
                        $order->save();
                        Stock::where(['model_id' => $order->id, 'model_type' => Order::class, 'status' => Status::INACTIVE])?->update(['status' => Status::ACTIVE]);
                        $paymentAttemptService->markSucceeded($order, 'cashondelivery', (string) $request->token);
                        $capturePaymentNotification->delete();
                        $this->response = true;
                    } elseif ($order->active == Ask::YES && $paymentAttemptService->succeededProviderTransactionExists($order, 'cashondelivery', (string) $request->token)) {
                        $this->response = true;
                    }
                }
            });

            if ($this->response && $order->active == Ask::YES) {
                return redirect()->route('payment.successful', ['order' => $order])->with('success', trans('all.message.payment_successful'));
            }
            return redirect()->route('payment.fail', ['order' => $order, 'paymentGateway' => 'cashondelivery'])->with('error', trans('all.message.something_wrong'));
        } catch (Exception $e) {
            Log::info($e->getMessage());
            DB::rollBack();
            return redirect()->route('payment.fail', ['order' => $order, 'paymentGateway' => 'cashondelivery'])->with('error', $e->getMessage());
        }
    }

    public function fail($order, $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('payment.index', ['order' => $order, 'paymentGateway' => 'cashondelivery'])->with('error', trans('all.message.something_wrong'));
    }

    public function cancel($order, $request): \Illuminate\Foundation\Application|\Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        return redirect('/checkout/payment');
    }
}
