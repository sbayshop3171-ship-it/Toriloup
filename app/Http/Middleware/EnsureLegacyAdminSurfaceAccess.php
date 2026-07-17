<?php

namespace App\Http\Middleware;

use App\Models\TenantMember;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegacyAdminSurfaceAccess
{
    private const MERCHANT_ALLOWED_PREFIXES = [
        'dashboard',
        'setting/product-category',
        'setting/product-brand',
        'setting/product-attribute',
        'setting/product-attribute-option',
        'setting/supplier',
        'setting/unit',
        'setting/return-reason',
        'setting/barcode',
        'country-code',
        'product',
        'purchase',
        'damage',
        'stock',
        'reviews',
        'pos',
        'pos-order',
        'online-order',
        'return-order',
        'return-and-refund',
        'coupon',
        'promotion',
        'product-section',
        'subscriber',
        'administrator',
        'customer',
        'transaction',
        'sales-report',
        'products-report',
    ];

    private const MERCHANT_GET_ONLY_PREFIXES = [
        'setting/role',
    ];

    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $ownerHosts = $this->ownerHosts();
        $merchantHost = strtolower((string) config('saas.merchant_host'));

        if (in_array($host, $ownerHosts, true)) {
            if (!$request->user()?->tokenCan('surface:platform')) {
                return response()->json([
                    'message' => 'Forbidden. platform token required.',
                ], 403);
            }

            $this->setSurface('platform', $request);
            return $next($request);
        }

        if ($merchantHost !== '' && $host === $merchantHost) {
            if (!$this->isMerchantAllowedPath($request)) {
                return $this->notFound($request, 'Merchant legacy admin route not available.');
            }

            if (!$request->user()?->tokenCan('surface:merchant')) {
                return response()->json([
                    'message' => 'Forbidden. merchant token required.',
                ], 403);
            }

            $tenant = $this->tenantContext->hydrateFromRequest($request);

            if ($tenant === null) {
                $tenantSlug = trim((string) ($request->headers->get('X-Tenant-Slug') ?: $request->query('tenant_slug', '')));

                $membership = TenantMember::query()
                    ->with(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])
                    ->where('user_id', $request->user()->getKey())
                    ->where('status', 'active');

                if ($tenantSlug !== '') {
                    $membership->whereHas('tenant', fn ($query) => $query->where('slug', $tenantSlug));
                }

                $tenantMember = $membership->orderBy('id')->first();

                if ($tenantMember?->tenant !== null) {
                    $this->tenantContext->set($tenantMember->tenant, $tenantMember->tenant->domains->first(), $request);
                    $tenant = $tenantMember->tenant;
                }
            }

            if ($tenant === null || $tenant->status !== 'active') {
                return response()->json([
                    'message' => 'No active merchant tenant context found.',
                ], 403);
            }

            $this->setSurface('merchant', $request);

            return $next($request);
        }

        return $this->notFound($request, 'Admin host mismatch.');
    }

    private function isMerchantAllowedPath(Request $request): bool
    {
        $path = trim($request->path(), '/');
        $adminPath = str_starts_with($path, 'api/admin/')
            ? substr($path, strlen('api/admin/'))
            : '';

        foreach (self::MERCHANT_ALLOWED_PREFIXES as $prefix) {
            if ($adminPath === $prefix || str_starts_with($adminPath, $prefix.'/')) {
                return true;
            }
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            foreach (self::MERCHANT_GET_ONLY_PREFIXES as $prefix) {
                if ($adminPath === $prefix || str_starts_with($adminPath, $prefix.'/')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function setSurface(string $surface, Request $request): void
    {
        $request->attributes->set(config('tenancy.surface_request_attribute', 'saas.surface'), $surface);
        app()->instance('saas.currentSurface', $surface);
    }

    private function notFound(Request $request, string $message): Response
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 404)
            : abort(404);
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
