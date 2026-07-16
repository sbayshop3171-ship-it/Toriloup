<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeImageRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\ProductOfferRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Requests\ShippingAndReturnRequest;
use App\Http\Resources\ProductAdminResource;
use App\Http\Resources\ProductDetailsAdminResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\Saas\SubscriptionManagerService;
use App\Services\Tenancy\TenantContext;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionManagerService $subscriptionManagerService,
    ) {
    }

    public function index(PaginateRequest $request)
    {
        try {
            return ProductAdminResource::collection($this->productService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(int $productId)
    {
        try {
            return new ProductDetailsAdminResource($this->productService->show(Product::query()->findOrFail($productId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function store(ProductRequest $request)
    {
        try {
            $tenant = $this->tenantContext->current($request);

            if ($tenant !== null) {
                $this->subscriptionManagerService->enforceLimit($tenant, 'products', 1, 'Your current plan product limit has been reached.');
            }

            return new ProductAdminResource($this->productService->store($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(ProductRequest $request, int $productId)
    {
        try {
            return new ProductAdminResource($this->productService->update($request, Product::query()->findOrFail($productId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroy(int $productId)
    {
        try {
            $this->productService->destroy(Product::query()->findOrFail($productId));
            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function uploadImage(ChangeImageRequest $request, int $productId)
    {
        try {
            return new ProductDetailsAdminResource($this->productService->uploadImage($request, Product::query()->findOrFail($productId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function generateSku()
    {
        try {
            return response(['data' => ['product_sku' => $this->productService->generateSku()]], 200);
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function shippingAndReturn(ShippingAndReturnRequest $request, int $productId)
    {
        try {
            return new ProductAdminResource($this->productService->shippingAndReturn($request, Product::query()->findOrFail($productId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function offer(ProductOfferRequest $request, int $productId)
    {
        try {
            return new ProductAdminResource($this->productService->productOffer($request, Product::query()->findOrFail($productId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function clearOffer(int $productId)
    {
        try {
            return new ProductAdminResource($this->productService->clearOffer(Product::query()->findOrFail($productId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
