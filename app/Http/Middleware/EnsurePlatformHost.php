<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedHost = strtolower((string) config('saas.owner_host'));
        $actualHost   = strtolower($request->getHost());

        if ($expectedHost !== '' && $actualHost !== $expectedHost) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Platform host mismatch.'], 404)
                : abort(404);
        }

        return $next($request);
    }
}
