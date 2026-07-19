<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SignupController;
use App\Http\Controllers\Controller;
use App\Http\Requests\SignupRequest;
use App\Models\User;
use App\Services\Saas\SurfaceTokenService;
use App\Services\Saas\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontAuthController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $tenantProvisioningService,
        private readonly SurfaceTokenService $surfaceTokenService,
    ) {
    }

    public function login(Request $request, LoginController $loginController): JsonResponse
    {
        $request->merge(['context' => 'customer']);

        $response = $loginController->login($request);
        $this->syncShadowCustomerFromRequest($request);

        return $this->augmentResponse($request, $response);
    }

    public function loginVerify(Request $request, SignupController $signupController): JsonResponse
    {
        $response = $signupController->signupLoginVerify($request);
        $this->syncShadowCustomerFromRequest($request);

        return $this->augmentResponse($request, $response);
    }

    public function register(SignupRequest $request, SignupController $signupController)
    {
        $response = $signupController->register($request);
        $this->syncShadowCustomerFromRequest($request);

        return $response;
    }

    public function resetPassword(Request $request, ForgotPasswordController $forgotPasswordController): JsonResponse
    {
        $response = $forgotPasswordController->resetPassword($request);
        $this->syncShadowCustomerFromRequest($request);

        return $this->augmentResponse($request, $response);
    }

    public function me(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));
        $tenantDomain = $request->attributes->get(config('tenancy.tenant_domain_attribute', 'saas.tenant_domain'));

        return response()->json([
            'status' => true,
            'surface' => 'storefront',
            'user' => $request->user(),
            'tenant' => $tenant?->only(['id', 'name', 'slug', 'status']),
            'domain' => $tenantDomain?->only(['hostname', 'domain_type', 'is_primary', 'is_fallback']),
        ]);
    }

    private function syncShadowCustomerFromRequest(Request $request): void
    {
        $tenant = $request->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));

        if ($tenant === null) {
            return;
        }

        $user = blank($request->email)
            ? \App\Models\User::query()->where('phone', $request->phone)->where('country_code', $request->country_code)->first()
            : \App\Models\User::query()->where('email', $request->email)->first();

        if ($user !== null) {
            $this->tenantProvisioningService->syncShadowCustomer($user, $tenant);
        }
    }

    private function augmentResponse(Request $request, JsonResponse $response): JsonResponse
    {
        $payload = json_decode((string) $response->getContent(), true) ?? [];
        $tenant = $request->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));
        $tenantDomain = $request->attributes->get(config('tenancy.tenant_domain_attribute', 'saas.tenant_domain'));
        $user = $this->resolveAuthUserFromRequest($request);

        $payload['surface'] = 'storefront';
        $payload['tenant'] = $tenant?->only(['id', 'name', 'slug', 'status']);
        $payload['domain'] = $tenantDomain?->only(['hostname', 'domain_type', 'is_primary', 'is_fallback']);

        if (array_key_exists('token', $payload) && $user !== null) {
            $payload['token'] = $this->surfaceTokenService->replaceLatestLegacyToken($user, 'storefront');
        }

        $response->setData($payload);

        return $response;
    }

    private function resolveAuthUserFromRequest(Request $request): ?User
    {
        return blank($request->email)
            ? \App\Models\User::query()->where('phone', $request->phone)->where('country_code', $request->country_code)->first()
            : \App\Models\User::query()->where('email', $request->email)->first();
    }
}
