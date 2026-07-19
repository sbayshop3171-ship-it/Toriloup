<?php

namespace App\Http\Controllers\Saas;

use App\Enums\Role;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\MerchantRegisterRequest;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\Saas\AdminSurfacePayloadService;
use App\Services\Saas\PlatformAuditLogService;
use App\Services\Saas\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminSurfaceAuthController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $tenantProvisioningService,
        private readonly AdminSurfacePayloadService $adminSurfacePayloadService,
        private readonly PlatformAuditLogService $platformAuditLogService,
    ) {
    }

    public function platformLogin(Request $request): JsonResponse
    {
        $user = $this->authenticateAdminUser($request);

        if ((int) $user->myRole !== Role::ADMIN) {
            return response()->json([
                'errors' => ['validation' => 'Only owner-level admin accounts can access the platform login.'],
            ], 403);
        }

        return response()->json($this->adminSurfacePayloadService->payloadFor($user, 'platform'), 201);
    }

    public function merchantLogin(Request $request): JsonResponse
    {
        $user = $this->authenticateAdminUser($request);

        if ((int) $user->myRole === Role::ADMIN) {
            return response()->json([
                'errors' => ['validation' => 'Owner accounts must sign in through owner.company.com only.'],
            ], 403);
        }

        $tenantMembers = $this->adminSurfacePayloadService->activeTenantMembers($user);

        if ($tenantMembers->isEmpty()) {
            return response()->json([
                'errors' => ['validation' => 'Merchant account is not attached to any active store.'],
            ], 403);
        }

        return response()->json($this->adminSurfacePayloadService->payloadFor($user, 'merchant'), 201);
    }

    public function merchantRegister(MerchantRegisterRequest $request): JsonResponse
    {
        $payload = $request->validated();

        if (blank($payload['primary_currency_code'] ?? null) && blank($payload['country_code'] ?? null)) {
            $payload['_detected_country_code'] = $this->detectedCountryCode($request);
        }

        $result = $this->tenantProvisioningService->registerMerchant($payload);

        return response()->json($this->adminSurfacePayloadService->payloadFor($result['user'], 'merchant', [
            'tenant' => $this->adminSurfacePayloadService->serializeTenant($result['tenant']),
            'domain' => $result['domain']->only(['hostname', 'domain_type', 'is_primary', 'is_fallback', 'verification_status']),
            'auto_live_checks' => $result['checks'],
        ]), 201);
    }

    private function detectedCountryCode(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Country-Code', 'X-App-Country'] as $header) {
            $countryCode = strtoupper((string) $request->headers->get($header, ''));

            if (preg_match('/^[A-Z]{2}$/', $countryCode) && $countryCode !== 'XX') {
                return $countryCode;
            }
        }

        $locale = strtoupper((string) $request->headers->get('Accept-Language', ''));

        if (preg_match('/(?:^|[-_,;])([A-Z]{2})(?:[,;]|$)/', $locale, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    public function merchantImpersonate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'max:120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $handoff = Cache::pull('merchant-impersonation:'.$request->string('token'));

        if (!is_array($handoff)) {
            return response()->json([
                'message' => 'Impersonation link is invalid or expired.',
            ], 410);
        }

        $tenant = Tenant::query()
            ->with(['domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])
            ->find($handoff['tenant_id'] ?? null);
        $user = User::query()->with('roles')->find($handoff['user_id'] ?? null);

        if (!$tenant instanceof Tenant || !$user instanceof User) {
            return response()->json([
                'message' => 'Merchant account could not be found.',
            ], 404);
        }

        if ($tenant->status !== 'active') {
            return response()->json([
                'message' => 'Tenant is not active.',
            ], 423);
        }

        $member = TenantMember::query()
            ->with(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback'), 'role'])
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$member instanceof TenantMember) {
            return response()->json([
                'message' => 'Merchant account is not attached to this active store.',
            ], 403);
        }

        $actor = isset($handoff['actor_user_id'])
            ? User::query()->find($handoff['actor_user_id'])
            : null;

        $this->platformAuditLogService->log(
            'platform.tenant.impersonation.started',
            'tenant',
            $tenant->id,
            [],
            [
                'merchant_user_id' => $user->id,
                'reason' => $handoff['reason'] ?? null,
                'handoff_created_at' => $handoff['created_at'] ?? null,
            ],
            $request,
            $actor,
            $tenant
        );

        return response()->json($this->adminSurfacePayloadService->payloadFor($user, 'merchant', [
            'current_tenant' => $this->adminSurfacePayloadService->serializeTenantMembership($member),
            'impersonation' => [
                'active' => true,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'actor_user_id' => $handoff['actor_user_id'] ?? null,
                'actor_name' => $handoff['actor_name'] ?? 'Platform Admin',
                'actor_email' => $handoff['actor_email'] ?? null,
                'reason' => $handoff['reason'] ?? null,
                'started_at' => now()->toDateTimeString(),
            ],
        ]), 201);
    }

    public function me(Request $request): JsonResponse
    {
        $surface = (string) $request->route('surface', 'merchant');
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json($this->adminSurfacePayloadService->mePayload($user, $surface));
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->user()?->currentAccessToken() !== null) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => trans('all.message.logout_success'),
        ]);
    }

    private function authenticateAdminUser(Request $request): User
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => $request['phone'] ? ['nullable', 'string', 'email', 'max:255'] : ['required', 'string', 'email', 'max:255'],
                'phone' => $request['email'] ? ['nullable', 'string', 'max:20'] : ['required', 'string', 'max:20'],
                'country_code' => $request['email'] ? ['nullable', 'string', 'max:20'] : ['required', 'string', 'max:20'],
                'password' => ['required', 'string', 'min:6'],
            ],
        );

        if ($validator->fails()) {
            return abort(response()->json([
                'errors' => $validator->errors(),
            ], 422));
        }

        $user = blank($request->email)
            ? User::query()->where('phone', $request->phone)->where('country_code', $request->country_code)->first()
            : User::query()->where('email', $request->email)->first();

        if ($user === null || (int) $user->status !== Status::ACTIVE || !Hash::check((string) $request->password, (string) $user->password)) {
            return abort(response()->json([
                'errors' => ['validation' => trans('all.message.credentials_invalid')],
            ], 400));
        }

        if (!isset($user->roles[0]) || (int) $user->myRole === Role::CUSTOMER) {
            return abort(response()->json([
                'errors' => ['validation' => 'Customer account cannot access admin panel login.'],
            ], 403));
        }

        return $user;
    }
}
