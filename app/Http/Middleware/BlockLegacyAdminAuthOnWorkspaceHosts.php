<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockLegacyAdminAuthOnWorkspaceHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower((string) $request->getHost());
        $ownerHosts = $this->ownerHosts();
        $merchantHost = strtolower((string) config('saas.merchant_host'));

        if (in_array($host, $ownerHosts, true) || ($merchantHost !== '' && $host === $merchantHost)) {
            return response()->json([
                'success' => false,
                'message' => 'Legacy auth endpoints are disabled on admin hosts. Use the surface-specific auth routes instead.',
            ], 404);
        }

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function ownerHosts(): array
    {
        return array_values(array_filter(array_unique(array_map(
            static fn (mixed $host): string => strtolower(trim((string) $host)),
            array_merge([(string) config('saas.owner_host')], (array) config('saas.owner_host_aliases', []))
        ))));
    }
}
