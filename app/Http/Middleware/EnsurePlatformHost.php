<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedHosts = $this->platformHosts();
        $actualHost    = strtolower($request->getHost());

        if ($expectedHosts !== [] && !in_array($actualHost, $expectedHosts, true)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Platform host mismatch.'], 404)
                : abort(404);
        }

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function platformHosts(): array
    {
        return array_values(array_filter(array_unique(array_map(
            static fn (mixed $host): string => strtolower(trim((string) $host)),
            array_merge([(string) config('saas.owner_host')], (array) config('saas.owner_host_aliases', []))
        ))));
    }
}
