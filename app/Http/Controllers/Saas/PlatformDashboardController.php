<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Services\Saas\PlatformTenantInsightService;
use Illuminate\Http\JsonResponse;

class PlatformDashboardController extends Controller
{
    public function __construct(
        private readonly PlatformTenantInsightService $platformTenantInsightService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return response()->json(array_merge([
            'status' => true,
        ], $this->platformTenantInsightService->overview()));
    }
}
