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
use App\Services\ProductBrandService;
use App\Services\ProductCategoryService;
use App\Services\UnitService;
use Exception;

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
}
