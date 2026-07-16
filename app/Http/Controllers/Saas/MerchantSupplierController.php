<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\SupplierService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantSupplierController extends Controller
{
    public function __construct(private readonly SupplierService $supplierService)
    {
    }

    public function index(PaginateRequest $request)
    {
        try {
            return SupplierResource::collection($this->supplierService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(int $supplierId)
    {
        try {
            return new SupplierResource($this->supplierService->show(Supplier::query()->findOrFail($supplierId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function store(SupplierRequest $request)
    {
        try {
            return new SupplierResource($this->supplierService->store($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(SupplierRequest $request, int $supplierId)
    {
        try {
            return new SupplierResource($this->supplierService->update($request, Supplier::query()->findOrFail($supplierId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroy(int $supplierId)
    {
        try {
            $this->supplierService->destroy(Supplier::query()->findOrFail($supplierId));
            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
