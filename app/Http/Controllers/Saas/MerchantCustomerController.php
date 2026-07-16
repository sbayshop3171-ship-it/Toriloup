<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\MerchantCustomerResource;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use App\Services\Saas\MerchantCustomerService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantCustomerController extends Controller
{
    public function __construct(
        private readonly MerchantCustomerService $merchantCustomerService,
        private readonly OrderService $orderService,
    ) {
    }

    public function index(PaginateRequest $request)
    {
        try {
            return MerchantCustomerResource::collection($this->merchantCustomerService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(int $customerId)
    {
        try {
            return new MerchantCustomerResource($this->merchantCustomerService->show($customerId));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function orders(PaginateRequest $request, int $customerId)
    {
        try {
            $customer = $this->merchantCustomerService->show($customerId);

            if ($customer->legacyUser === null) {
                return OrderResource::collection(collect());
            }

            return OrderResource::collection($this->orderService->userOrder($request, $customer->legacyUser));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
