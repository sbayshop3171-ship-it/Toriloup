<?php

namespace App\Http\Controllers\Saas;

use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\OrderStatusRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\PaymentStatusRequest;
use App\Http\Requests\PosOrderRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderDetailsResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\Saas\TenantProvisioningService;
use App\Services\Tenancy\TenantContext;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantPosController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CustomerService $customerService,
        private readonly TenantProvisioningService $tenantProvisioningService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function store(PosOrderRequest $request)
    {
        try {
            return new OrderDetailsResource($this->orderService->posOrderStore($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function storeCustomer(CustomerRequest $request)
    {
        try {
            $customer = $this->customerService->store($request);
            $tenant = $this->tenantContext->current($request);

            if ($tenant !== null) {
                $this->tenantProvisioningService->syncShadowCustomer($customer, $tenant);
            }

            return new CustomerResource($customer);
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function orders(PaginateRequest $request)
    {
        try {
            $request->merge(['order_type' => OrderType::POS]);

            return OrderResource::collection($this->orderService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function showOrder(int $orderId)
    {
        try {
            return new OrderDetailsResource($this->orderService->show(Order::query()->where('order_type', OrderType::POS)->findOrFail($orderId), false));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroyOrder(int $orderId)
    {
        try {
            $this->orderService->destroy(Order::query()->where('order_type', OrderType::POS)->findOrFail($orderId));
            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function changeOrderStatus(OrderStatusRequest $request, int $orderId)
    {
        try {
            return new OrderDetailsResource($this->orderService->changeStatus(Order::query()->where('order_type', OrderType::POS)->findOrFail($orderId), $request, false));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function changePaymentStatus(PaymentStatusRequest $request, int $orderId)
    {
        try {
            return new OrderDetailsResource($this->orderService->changePaymentStatus(Order::query()->where('order_type', OrderType::POS)->findOrFail($orderId), $request, false));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
