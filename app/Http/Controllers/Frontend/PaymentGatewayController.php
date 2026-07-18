<?php

namespace App\Http\Controllers\Frontend;


use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\SimplePaymentGatewayResource;
use App\Models\PaymentGateway;
use App\Models\TenantPaymentMethod;
use App\Services\PaymentGatewayService;
use App\Services\Saas\TenantPaymentMethodCatalogService;
use App\Services\Tenancy\TenantContext;
use Exception;


class PaymentGatewayController extends AdminController
{
    private PaymentGatewayService $paymentGatewayService;

    public function __construct(
        PaymentGatewayService $paymentGatewayService,
        private readonly TenantContext $tenantContext,
        private readonly TenantPaymentMethodCatalogService $tenantPaymentMethodCatalogService,
    )
    {
        parent::__construct();
        $this->paymentGatewayService = $paymentGatewayService;
    }

    public function index(PaginateRequest $request): \Illuminate\Http\Response|\Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            $tenant = $this->tenantContext->current();

            if ($tenant !== null) {
                $methods = $this->tenantPaymentMethodCatalogService->activeMethodsForTenant($tenant);
                $methodsBySlug = $methods->keyBy(
                    fn (TenantPaymentMethod $method): string => $this->tenantPaymentMethodCatalogService->gatewaySlugForProviderCode($method->provider_code)
                );

                $gateways = PaymentGateway::query()
                    ->with('media')
                    ->whereIn('slug', $methodsBySlug->keys()->all())
                    ->where('status', $request->get('status', 5))
                    ->orderBy('id')
                    ->get()
                    ->each(function (PaymentGateway $gateway) use ($methodsBySlug): void {
                        $method = $methodsBySlug->get($gateway->slug);

                        if ($method instanceof TenantPaymentMethod) {
                            $gateway->setAttribute('tenant_display_name', $method->display_name);
                            $gateway->setAttribute('tenant_checkout_label', $method->checkout_label);
                            $gateway->setAttribute('tenant_provider_code', $method->provider_code);
                        }
                    });

                return SimplePaymentGatewayResource::collection($gateways);
            }

            return SimplePaymentGatewayResource::collection($this->paymentGatewayService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
