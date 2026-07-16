<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\StockResource;
use App\Services\StockService;
use Exception;

class MerchantStockController extends Controller
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    public function index(PaginateRequest $request)
    {
        try {
            return StockResource::collection($this->stockService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
