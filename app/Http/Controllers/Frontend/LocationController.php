<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\IpLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LocationController extends Controller
{
    public function detect(Request $request, IpLocationService $ipLocationService)
    {
        try {
            return response(['data' => $ipLocationService->detect($request)]);
        } catch (Throwable $exception) {
            Log::debug('Frontend IP location detect failed', [
                'message' => $exception->getMessage(),
            ]);

            return response(['data' => null]);
        }
    }
}
