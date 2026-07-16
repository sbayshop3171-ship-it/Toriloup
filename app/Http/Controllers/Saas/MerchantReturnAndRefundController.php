<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderStatusRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\ReturnAndRefundDetailsResource;
use App\Http\Resources\ReturnAndRefundResource;
use App\Models\ReturnAndRefund;
use App\Services\ReturnAndRefundService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantReturnAndRefundController extends Controller
{
    public function __construct(private readonly ReturnAndRefundService $returnAndRefundService)
    {
    }

    public function index(PaginateRequest $request)
    {
        try {
            return ReturnAndRefundResource::collection($this->returnAndRefundService->list($request, false));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(int $returnAndRefundId)
    {
        try {
            return new ReturnAndRefundDetailsResource($this->returnAndRefundService->show(ReturnAndRefund::query()->findOrFail($returnAndRefundId), false));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function changeStatus(OrderStatusRequest $request, int $returnAndRefundId)
    {
        try {
            return new ReturnAndRefundDetailsResource($this->returnAndRefundService->changeStatus(ReturnAndRefund::query()->findOrFail($returnAndRefundId), $request));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
