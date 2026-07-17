<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyRequestSurface
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $path = trim($request->path(), '/');
        $ownerHosts = $this->ownerHosts();

        $surface = match (true) {
            $path === 'api/system' || str_starts_with($path, 'api/system/') => 'system',
            in_array($host, $ownerHosts, true) || $path === 'api/platform' || str_starts_with($path, 'api/platform/') => 'platform',
            $host === strtolower((string) config('saas.merchant_host')) || $path === 'api/merchant' || str_starts_with($path, 'api/merchant/') => 'merchant',
            $path === 'api/storefront' || str_starts_with($path, 'api/storefront/') => 'storefront',
            $host === strtolower((string) config('saas.marketing_host')) => 'marketing',
            default => 'storefront',
        };

        $attribute = config('tenancy.surface_request_attribute', 'saas.surface');

        $request->attributes->set($attribute, $surface);
        app()->instance('saas.currentSurface', $surface);

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
