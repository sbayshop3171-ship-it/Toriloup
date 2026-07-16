<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\PurchasePaymentRequest;
use App\Http\Requests\PurchaseRequest;
use App\Http\Resources\PurchaseDetailsResource;
use App\Http\Resources\PurchasePaymentResource;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Services\PurchaseService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantPurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchaseService)
    {
    }

    public function index(PaginateRequest $request)
    {
        try {
            return PurchaseResource::collection($this->purchaseService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function store(PurchaseRequest $request)
    {
        try {
            return new PurchaseResource($this->purchaseService->store($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(int $purchaseId)
    {
        try {
            return new PurchaseDetailsResource($this->purchaseService->show(Purchase::query()->findOrFail($purchaseId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function edit(int $purchaseId)
    {
        try {
            return new PurchaseDetailsResource($this->purchaseService->edit(Purchase::query()->findOrFail($purchaseId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(PurchaseRequest $request, int $purchaseId)
    {
        try {
            return new PurchaseResource($this->purchaseService->update($request, Purchase::query()->findOrFail($purchaseId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroy(int $purchaseId)
    {
        try {
            $this->purchaseService->destroy(Purchase::query()->findOrFail($purchaseId));
            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function payment(PurchasePaymentRequest $request, int $purchaseId)
    {
        try {
            $request->merge(['purchase_id' => $purchaseId]);

            return new PurchaseResource($this->purchaseService->payment($request, Purchase::query()->findOrFail($purchaseId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function paymentHistory(int $purchaseId)
    {
        try {
            return PurchasePaymentResource::collection($this->purchaseService->paymentHistory(Purchase::query()->findOrFail($purchaseId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function paymentDestroy(int $purchaseId, int $paymentId)
    {
        try {
            $this->purchaseService->paymentDestroy(
                Purchase::query()->findOrFail($purchaseId),
                PurchasePayment::query()->findOrFail($paymentId)
            );

            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
