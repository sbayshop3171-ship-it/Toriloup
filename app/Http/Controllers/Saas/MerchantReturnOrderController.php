<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\ReturnOrderRequest;
use App\Http\Resources\ReturnOrderDetailsResource;
use App\Http\Resources\ReturnOrderResource;
use App\Models\ReturnOrder;
use App\Services\ReturnOrderService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantReturnOrderController extends Controller
{
    public function __construct(private readonly ReturnOrderService $returnOrderService)
    {
    }

    public function index(PaginateRequest $request)
    {
        try {
            return ReturnOrderResource::collection($this->returnOrderService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function store(ReturnOrderRequest $request)
    {
        try {
            return new ReturnOrderResource($this->returnOrderService->store($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(int $returnOrderId)
    {
        try {
            return new ReturnOrderDetailsResource($this->returnOrderService->show(ReturnOrder::query()->findOrFail($returnOrderId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function edit(int $returnOrderId)
    {
        try {
            return new ReturnOrderDetailsResource($this->returnOrderService->edit(ReturnOrder::query()->findOrFail($returnOrderId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(ReturnOrderRequest $request, int $returnOrderId)
    {
        try {
            return new ReturnOrderResource($this->returnOrderService->update($request, ReturnOrder::query()->findOrFail($returnOrderId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroy(int $returnOrderId)
    {
        try {
            $this->returnOrderService->destroy(ReturnOrder::query()->findOrFail($returnOrderId));
            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
