<?php

namespace App\Services;

use App\Http\Requests\PermissionRequest;
use App\Libraries\AppLibrary;
use App\Libraries\QueryExceptionLibrary;
use App\Services\Tenancy\TenantContext;
use Exception;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionService
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    /**
     * @throws Exception
     */
    public function permission(Role $role, bool $strictTenantRole = true): object
    {
        try {
            if ($strictTenantRole) {
                $this->ensureRoleBelongsToCurrentSurface($role);
            }

            $permissions     = Permission::get();
            $rolePermissions = Permission::join(
                "role_has_permissions",
                "role_has_permissions.permission_id",
                "=",
                "permissions.id"
            )->where("role_has_permissions.role_id", $role->id)->get()->pluck('name', 'id');
            return AppLibrary::permissionWithAccess($permissions, $rolePermissions);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(PermissionRequest $request, Role $role): Role
    {
        try {
            $this->ensureRoleBelongsToCurrentSurface($role);

            return $role->syncPermissions(Permission::whereIn('id', $request->get('permissions'))->get());
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    private function ensureRoleBelongsToCurrentSurface(Role $role): void
    {
        $tenantId = null;

        if (app()->bound('saas.currentSurface') && app('saas.currentSurface') === 'merchant') {
            $tenantId = $this->tenantContext->currentId();
        }

        if ($tenantId !== null && (int) $role->tenant_id !== $tenantId) {
            throw new Exception('Role not found for this merchant tenant.', 404);
        }

        if ($tenantId === null && $role->tenant_id !== null) {
            throw new Exception('Tenant role is not available on the owner workspace.', 404);
        }
    }
}
