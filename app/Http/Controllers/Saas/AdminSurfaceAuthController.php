<?php

namespace App\Http\Controllers\Saas;

use App\Enums\Role;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\MerchantRegisterRequest;
use App\Http\Resources\MenuResource;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\UserResource;
use App\Libraries\AppLibrary;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\MenuService;
use App\Services\PermissionService;
use App\Services\Saas\SurfaceTokenService;
use App\Services\Saas\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminSurfaceAuthController extends Controller
{
    public function __construct(
        private readonly MenuService $menuService,
        private readonly PermissionService $permissionService,
        private readonly TenantProvisioningService $tenantProvisioningService,
        private readonly SurfaceTokenService $surfaceTokenService,
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

        return response()->json($this->buildAdminPayload($user, 'platform'), 201);
    }

    public function merchantLogin(Request $request): JsonResponse
    {
        $user = $this->authenticateAdminUser($request);
        $tenantMembers = $this->activeTenantMembers($user);

        if ($tenantMembers->isEmpty()) {
            return response()->json([
                'errors' => ['validation' => 'Merchant account is not attached to any active store.'],
            ], 403);
        }

        return response()->json($this->buildAdminPayload($user, 'merchant', [
            'tenants' => $tenantMembers->map(fn (TenantMember $member) => $this->serializeTenantMembership($member))->values()->all(),
            'current_tenant' => $this->serializeTenantMembership($tenantMembers->first()),
        ]), 201);
    }

    public function merchantRegister(MerchantRegisterRequest $request): JsonResponse
    {
        $result = $this->tenantProvisioningService->registerMerchant($request->validated());

        return response()->json($this->buildAdminPayload($result['user'], 'merchant', [
            'tenant' => $this->serializeTenant($result['tenant']),
            'domain' => $result['domain']->only(['hostname', 'domain_type', 'is_primary', 'is_fallback', 'verification_status']),
            'auto_live_checks' => $result['checks'],
        ]), 201);
    }

    public function me(Request $request): JsonResponse
    {
        $surface = (string) $request->route('surface', 'merchant');
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $payload = [
            'surface' => $surface,
            'user' => (new UserResource($user))->resolve($request),
        ];

        if ($surface === 'merchant') {
            $tenantMembers = $this->activeTenantMembers($user);
            $payload['tenants'] = $tenantMembers->map(fn (TenantMember $member) => $this->serializeTenantMembership($member))->values()->all();
            $payload['current_tenant'] = $tenantMembers->isNotEmpty() ? $this->serializeTenantMembership($tenantMembers->first()) : null;
        }

        return response()->json($payload);
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

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function buildAdminPayload(User $user, string $surface, array $extra = []): array
    {
        $token = $this->surfaceTokenService->issueToken($user, $surface);
        $role = $user->roles[0];
        $permissionResource = PermissionResource::collection($this->permissionService->permission($role));
        $permission = $permissionResource->resolve(request());
        $defaultPermission = AppLibrary::defaultPermission($permissionResource->collection);
        $menu = MenuResource::collection(collect($this->menuService->menu($role)))->resolve(request());
        $defaultMenu = (object) AppLibrary::defaultMenu($this->menuService->menu($role), $defaultPermission);

        return array_merge([
            'message' => trans('all.message.login_success'),
            'token' => $token,
            'surface' => $surface,
            'user' => (new UserResource($user))->resolve(request()),
            'menu' => $menu,
            'permission' => $permission,
            'defaultPermission' => $defaultPermission,
            'defaultMenu' => $defaultMenu,
        ], $extra);
    }

    private function activeTenantMembers(User $user)
    {
        return TenantMember::query()
            ->with(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback'), 'role'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTenantMembership(TenantMember $member): array
    {
        return [
            'membership_id' => $member->id,
            'status' => $member->status,
            'role' => $member->role?->only(['id', 'code', 'name', 'scope']),
            'tenant' => $member->tenant ? $this->serializeTenant($member->tenant) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTenant($tenant): array
    {
        return [
            'id' => $tenant->id,
            'uuid' => $tenant->uuid,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'onboarding_status' => $tenant->onboarding_status,
            'plan_code' => $tenant->plan_code,
            'primary_domain' => $tenant->domains->first()?->hostname,
        ];
    }
}
