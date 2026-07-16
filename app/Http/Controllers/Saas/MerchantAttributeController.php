<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\ProductAttributeOptionRequest;
use App\Http\Requests\ProductAttributeRequest;
use App\Http\Resources\ProductAttributeOptionResource;
use App\Http\Resources\ProductAttributeResource;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeOption;
use App\Services\ProductAttributeOptionService;
use App\Services\ProductAttributeService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantAttributeController extends Controller
{
    public function __construct(
        private readonly ProductAttributeService $productAttributeService,
        private readonly ProductAttributeOptionService $productAttributeOptionService,
    ) {
    }

    public function index(PaginateRequest $request)
    {
        try {
            return ProductAttributeResource::collection($this->productAttributeService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(int $attributeId)
    {
        try {
            return new ProductAttributeResource($this->productAttributeService->show(ProductAttribute::query()->findOrFail($attributeId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function store(ProductAttributeRequest $request)
    {
        try {
            return new ProductAttributeResource($this->productAttributeService->store($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(ProductAttributeRequest $request, int $attributeId)
    {
        try {
            return new ProductAttributeResource($this->productAttributeService->update($request, ProductAttribute::query()->findOrFail($attributeId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroy(int $attributeId)
    {
        try {
            $this->productAttributeService->destroy(ProductAttribute::query()->findOrFail($attributeId));
            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function options(PaginateRequest $request, int $attributeId)
    {
        try {
            $attribute = ProductAttribute::query()->findOrFail($attributeId);

            return ProductAttributeOptionResource::collection($this->productAttributeOptionService->list($request, $attribute));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function showOption(int $attributeId, int $optionId)
    {
        try {
            $attribute = ProductAttribute::query()->findOrFail($attributeId);
            $option = ProductAttributeOption::query()->findOrFail($optionId);

            return new ProductAttributeOptionResource($this->productAttributeOptionService->show($attribute, $option));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function storeOption(ProductAttributeOptionRequest $request, int $attributeId)
    {
        try {
            $attribute = ProductAttribute::query()->findOrFail($attributeId);

            return new ProductAttributeOptionResource($this->productAttributeOptionService->store($request, $attribute));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function updateOption(ProductAttributeOptionRequest $request, int $attributeId, int $optionId)
    {
        try {
            $attribute = ProductAttribute::query()->findOrFail($attributeId);
            $option = ProductAttributeOption::query()->findOrFail($optionId);

            return new ProductAttributeOptionResource($this->productAttributeOptionService->update($request, $attribute, $option));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroyOption(int $attributeId, int $optionId)
    {
        try {
            $attribute = ProductAttribute::query()->findOrFail($attributeId);
            $option = ProductAttributeOption::query()->findOrFail($optionId);

            $this->productAttributeOptionService->destroy($attribute, $option);

            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
