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
        $ownerHost = strtolower((string) config('saas.owner_host'));
        $merchantHost = strtolower((string) config('saas.merchant_host'));

        if (($ownerHost !== '' && $host === $ownerHost) || ($merchantHost !== '' && $host === $merchantHost)) {
            return response()->json([
                'success' => false,
                'message' => 'Legacy auth endpoints are disabled on admin hosts. Use the surface-specific auth routes instead.',
            ], 404);
        }

        return $next($request);
    }
}
