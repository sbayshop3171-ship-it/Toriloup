<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\ProductBrandRequest;
use App\Http\Requests\ProductCategoryRequest;
use App\Http\Requests\UnitRequest;
use App\Http\Resources\ProductBrandResource;
use App\Http\Resources\ProductCategoryResource;
use App\Http\Resources\UnitResource;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Services\ProductBrandService;
use App\Services\ProductCategoryService;
use App\Services\UnitService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantCatalogController extends Controller
{
    public function __construct(
        private readonly ProductCategoryService $productCategoryService,
        private readonly ProductBrandService $productBrandService,
        private readonly UnitService $unitService,
    ) {
    }

    public function categories(PaginateRequest $request)
    {
        try {
            return ProductCategoryResource::collection($this->productCategoryService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function storeCategory(ProductCategoryRequest $request)
    {
        try {
            return new ProductCategoryResource($this->productCategoryService->store($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function showCategory(int $productCategory)
    {
        try {
            return new ProductCategoryResource($this->productCategoryService->show(ProductCategory::query()->findOrFail($productCategory)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function updateCategory(ProductCategoryRequest $request, int $productCategory)
    {
        try {
            return new ProductCategoryResource($this->productCategoryService->update($request, ProductCategory::query()->findOrFail($productCategory)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroyCategory(int $productCategory)
    {
        try {
            $this->productCategoryService->destroy(ProductCategory::query()->findOrFail($productCategory));

            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function brands(PaginateRequest $request)
    {
        try {
            return ProductBrandResource::collection($this->productBrandService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function storeBrand(ProductBrandRequest $request)
    {
        try {
            return new ProductBrandResource($this->productBrandService->store($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function showBrand(int $productBrand)
    {
        try {
            return new ProductBrandResource($this->productBrandService->show(ProductBrand::query()->findOrFail($productBrand)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function updateBrand(ProductBrandRequest $request, int $productBrand)
    {
        try {
            return new ProductBrandResource($this->productBrandService->update($request, ProductBrand::query()->findOrFail($productBrand)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroyBrand(int $productBrand)
    {
        try {
            $this->productBrandService->destroy(ProductBrand::query()->findOrFail($productBrand));

            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function units(PaginateRequest $request)
    {
        try {
            return UnitResource::collection($this->unitService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function storeUnit(UnitRequest $request)
    {
        try {
            return new UnitResource($this->unitService->store($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function showUnit(int $unit)
    {
        try {
            return new UnitResource($this->unitService->show(Unit::query()->findOrFail($unit)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function updateUnit(UnitRequest $request, int $unit)
    {
        try {
            return new UnitResource($this->unitService->update($request, Unit::query()->findOrFail($unit)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroyUnit(int $unit)
    {
        try {
            $this->unitService->destroy(Unit::query()->findOrFail($unit));

            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
