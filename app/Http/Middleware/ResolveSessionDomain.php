<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveSessionDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredDomain = config('session.domain');
        $rootDomain = $this->normalizeHost((string) config('saas.root_domain', ''));
        $requestHost = $this->normalizeHost($request->getHost());

        if ($configuredDomain && $rootDomain !== '' && $requestHost !== '' && !$this->belongsToRootDomain($requestHost, $rootDomain)) {
            config(['session.domain' => null]);
        }

        return $next($request);
    }

    private function belongsToRootDomain(string $host, string $rootDomain): bool
    {
        return $host === $rootDomain || Str::endsWith($host, '.'.$rootDomain);
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return trim($host, '. ');
    }
}
