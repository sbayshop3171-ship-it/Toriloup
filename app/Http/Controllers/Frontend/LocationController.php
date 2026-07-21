<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\IpLocationService;
use App\Services\ReverseGeocodingService;
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

    public function reverse(Request $request, ReverseGeocodingService $reverseGeocodingService)
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        try {
            return response([
                'data' => $reverseGeocodingService->reverse(
                    (float) $validated['latitude'],
                    (float) $validated['longitude'],
                    $validated['country_code'] ?? null
                ),
            ]);
        } catch (Throwable $exception) {
            Log::debug('Frontend reverse location lookup failed', [
                'message' => $exception->getMessage(),
            ]);

            return response(['data' => null]);
        }
    }
}
