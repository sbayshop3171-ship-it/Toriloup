<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\ProductVariationRequest;
use App\Http\Resources\ProductVariationResource;
use App\Http\Resources\SimpleProductVariationResource;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\ProductVariationService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class MerchantVariationController extends Controller
{
    public function __construct(private readonly ProductVariationService $productVariationService)
    {
    }

    public function index(PaginateRequest $request, int $productId)
    {
        try {
            return ProductVariationResource::collection($this->productVariationService->list($request, Product::query()->findOrFail($productId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(int $productId, int $variationId)
    {
        try {
            return new ProductVariationResource($this->productVariationService->show(
                Product::query()->findOrFail($productId),
                ProductVariation::query()->findOrFail($variationId)
            ));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function store(ProductVariationRequest $request, int $productId)
    {
        try {
            return ProductVariationResource::collection($this->productVariationService->store($request, Product::query()->findOrFail($productId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(ProductVariationRequest $request, int $productId, int $variationId)
    {
        try {
            return ProductVariationResource::collection($this->productVariationService->update(
                $request,
                Product::query()->findOrFail($productId),
                ProductVariation::query()->findOrFail($variationId)
            ));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroy(int $productId, int $variationId)
    {
        try {
            $this->productVariationService->destroy(
                Product::query()->findOrFail($productId),
                ProductVariation::query()->findOrFail($variationId)
            );

            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function tree(Request $request, int $productId)
    {
        try {
            return response(['data' => $this->productVariationService->tree($request, Product::query()->findOrFail($productId))]);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function singleTree(int $productId)
    {
        try {
            return response(['data' => $this->productVariationService->singleTree(Product::query()->findOrFail($productId))]);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function treeWithSelected(Request $request, int $productId)
    {
        try {
            return response(['data' => $this->productVariationService->treeWithSelected($request, Product::query()->findOrFail($productId))]);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function initialVariation(int $productId)
    {
        try {
            return SimpleProductVariationResource::collection($this->productVariationService->initialVariation(Product::query()->findOrFail($productId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function childrenVariation(int $variationId)
    {
        try {
            return SimpleProductVariationResource::collection($this->productVariationService->childrenVariation(ProductVariation::query()->findOrFail($variationId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function ancestorsToString(int $variationId)
    {
        try {
            return response(['data' => $this->productVariationService->ancestorsToString(ProductVariation::query()->findOrFail($variationId))], 200);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function ancestorsAndSelfId(int $variationId)
    {
        try {
            return response(['data' => $this->productVariationService->ancestorsAndSelfId(ProductVariation::query()->findOrFail($variationId))], 200);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
