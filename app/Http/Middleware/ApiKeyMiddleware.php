<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = data_get(config('installer'), 'buildPayload.license_code') ?: env('VITE_API_KEY');

        if ($request->hasHeader('x-api-key')) {
            if (hash_equals((string) $apiKey, (string) $request->header('x-api-key'))) {
                return $next($request);
            }
        }
        return response()->json(trans('all.message.invalid_api_key'), 400);
    }
}
