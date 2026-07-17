<?php

namespace App\Services\Saas;

use App\Http\Resources\MenuResource;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\UserResource;
use App\Libraries\AppLibrary;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\MenuService;
use App\Services\PermissionService;
use Illuminate\Support\Collection;

class AdminSurfacePayloadService
{
    public function __construct(
        private readonly MenuService $menuService,
        private readonly PermissionService $permissionService,
        private readonly SurfaceTokenService $surfaceTokenService,
        private readonly MerchantPermissionBootstrapper $merchantPermissionBootstrapper,
        private readonly PlatformSupportSessionService $platformSupportSessionService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function payloadFor(User $user, string $surface, array $extra = []): array
    {
        if ($surface === 'merchant') {
            $this->merchantPermissionBootstrapper->ensureManagerRoleHasStorePermissions();
            $user->load('roles');
        }

        $role = $user->roles[0];
        $permissionResource = PermissionResource::collection($this->permissionService->permission($role));
        $defaultPermission = AppLibrary::defaultPermission($permissionResource->collection);
        $menu = MenuResource::collection(collect($this->menuService->menu($role)))->resolve(request());
        $defaultMenu = (object) AppLibrary::defaultMenu($this->menuService->menu($role), $defaultPermission);
        $token = array_key_exists('token', $extra)
            ? $extra['token']
            : $this->surfaceTokenService->issueToken($user, $surface);

        $payload = [
            'message' => trans('all.message.login_success'),
            'token' => $token,
            'surface' => $surface,
            'user' => (new UserResource($user))->resolve(request()),
            'menu' => $menu,
            'permission' => $permissionResource->resolve(request()),
            'defaultPermission' => $defaultPermission,
            'defaultMenu' => $defaultMenu,
        ];

        if ($surface === 'merchant') {
            $tenantMembers = $this->activeTenantMembers($user);
            $payload['tenants'] = $tenantMembers
                ->map(fn (TenantMember $member) => $this->serializeTenantMembership($member))
                ->values()
                ->all();
            $payload['current_tenant'] = $tenantMembers->isNotEmpty()
                ? $this->serializeTenantMembership($tenantMembers->first())
                : null;
        }

        return array_merge($payload, $extra);
    }

    /**
     * @return array<string, mixed>
     */
    public function mePayload(User $user, string $surface): array
    {
        if ($surface === 'merchant') {
            $this->merchantPermissionBootstrapper->ensureManagerRoleHasStorePermissions();
            $user->load('roles');
        }

        $role = $user->roles[0] ?? null;
        $payload = [
            'surface' => $surface,
            'user' => (new UserResource($user))->resolve(request()),
        ];

        if ($role !== null) {
            $permissionResource = PermissionResource::collection($this->permissionService->permission($role));
            $payload['menu'] = MenuResource::collection(collect($this->menuService->menu($role)))->resolve(request());
            $payload['permission'] = $permissionResource->resolve(request());
            $payload['defaultPermission'] = AppLibrary::defaultPermission($permissionResource->collection);
            $payload['defaultMenu'] = (object) AppLibrary::defaultMenu($this->menuService->menu($role), $payload['defaultPermission']);
        }

        if ($surface === 'merchant') {
            $tenantMembers = $this->activeTenantMembers($user);
            $supportSession = $this->platformSupportSessionService->currentForToken(
                request()->user(),
                request()->user()?->currentAccessToken()?->id
            );

            $payload['tenants'] = $tenantMembers
                ->map(fn (TenantMember $member) => $this->serializeTenantMembership($member))
                ->values()
                ->all();
            $payload['current_tenant'] = $supportSession?->tenantMember
                ? $this->serializeTenantMembership($supportSession->tenantMember)
                : ($tenantMembers->isNotEmpty() ? $this->serializeTenantMembership($tenantMembers->first()) : null);
            $payload['support_session'] = $supportSession ? $this->platformSupportSessionService->serializeSession($supportSession) : null;
        }

        return $payload;
    }

    /**
     * @return Collection<int, TenantMember>
     */
    public function activeTenantMembers(User $user): Collection
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
    public function serializeTenantMembership(TenantMember $member): array
    {
        $tenantPayload = $member->tenant ? $this->serializeTenant($member->tenant) : null;

        return [
            'membership_id' => $member->id,
            'status' => $member->status,
            'role' => $member->role?->only(['id', 'code', 'name', 'scope']),
            'tenant_id' => $tenantPayload['id'] ?? null,
            'uuid' => $tenantPayload['uuid'] ?? null,
            'name' => $tenantPayload['name'] ?? null,
            'slug' => $tenantPayload['slug'] ?? null,
            'tenant_status' => $tenantPayload['status'] ?? null,
            'onboarding_status' => $tenantPayload['onboarding_status'] ?? null,
            'plan_code' => $tenantPayload['plan_code'] ?? null,
            'primary_domain' => $tenantPayload['primary_domain'] ?? null,
            'tenant' => $tenantPayload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeTenant($tenant): array
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
