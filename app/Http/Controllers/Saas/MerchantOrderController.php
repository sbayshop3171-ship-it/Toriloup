<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderStatusRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\PaymentStatusRequest;
use App\Http\Resources\OrderDetailsResource;
use App\Models\Order;
use App\Services\OrderService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantOrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function index(PaginateRequest $request)
    {
        try {
            return OrderDetailsResource::collection($this->orderService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(int $orderId)
    {
        try {
            return new OrderDetailsResource($this->orderService->show(Order::query()->findOrFail($orderId), false));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function changeStatus(OrderStatusRequest $request, int $orderId)
    {
        try {
            return new OrderDetailsResource($this->orderService->changeStatus(Order::query()->findOrFail($orderId), $request, false));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function changePaymentStatus(PaymentStatusRequest $request, int $orderId)
    {
        try {
            return new OrderDetailsResource($this->orderService->changePaymentStatus(Order::query()->findOrFail($orderId), $request, false));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
