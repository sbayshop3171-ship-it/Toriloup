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
    ) {
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function payloadFor(User $user, string $surface, array $extra = []): array
    {
        $role = $user->roles[0];
        $permissionResource = PermissionResource::collection($this->permissionService->permission($role));
        $defaultPermission = AppLibrary::defaultPermission($permissionResource->collection);
        $menu = MenuResource::collection(collect($this->menuService->menu($role)))->resolve(request());
        $defaultMenu = (object) AppLibrary::defaultMenu($this->menuService->menu($role), $defaultPermission);

        $payload = [
            'message' => trans('all.message.login_success'),
            'token' => $this->surfaceTokenService->issueToken($user, $surface),
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
        $payload = [
            'surface' => $surface,
            'user' => (new UserResource($user))->resolve(request()),
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
