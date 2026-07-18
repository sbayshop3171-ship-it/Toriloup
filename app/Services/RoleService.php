<?php

namespace App\Services;

use App\Enums\Role as EnumsRole;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\RoleRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Services\Tenancy\TenantContext;
use Exception;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    protected array $roleFilter = [
        'name'
    ];
    protected array $exceptFilter = [
        'excepts'
    ];
    protected array $roleArray = [
        EnumsRole::ADMIN,
        EnumsRole::CUSTOMER,
        EnumsRole::MANAGER,
        EnumsRole::POS_OPERATOR,
        EnumsRole::STUFF
    ];

    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request)
    {
        try {
            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_type') ?? 'asc';

            return Role::query()
                ->withCount(['users' => function ($query): void {
                    if ($tenantId = $this->currentTenantId()) {
                        $query->whereHas('tenantMembers', fn ($memberQuery) => $memberQuery->where('tenant_id', $tenantId));
                    }
                }])
                ->when($this->currentTenantId(), fn ($query, int $tenantId) => $query->where('tenant_id', $tenantId))
                ->when(!$this->currentTenantId(), fn ($query) => $query->whereNull('tenant_id'))
                ->where(function ($query) use ($requests) {
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->roleFilter)) {
                        $query->where($key, 'like', '%' . $request . '%');
                    }

                    if (in_array($key, $this->exceptFilter)) {
                        $explodes = explode('|', $request);
                        if (is_array($explodes)) {
                            foreach ($explodes as $explode) {
                                $query->where('id', '!=', $explode);
                            }
                        }
                    }
                }
            })
                ->orderBy($orderColumn, $orderType)
                ->$method($methodValue);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function store(RoleRequest $request)
    {
        try {
            return Role::query()->create($request->validated() + [
                'guard_name' => 'sanctum',
                'tenant_id' => $this->currentTenantId(),
            ]);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(RoleRequest $request, Role $role)
    {
        try {
            $this->ensureRoleBelongsToCurrentSurface($role);

            return tap($role)->update($request->validated());
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function destroy(Role $role): void
    {
        try {
            $this->ensureRoleBelongsToCurrentSurface($role);

            if (!in_array($role->id, $this->roleArray)) {
                $role->delete();
            } else {
                throw new Exception("This role not deletable", 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function show(Role $role): Role
    {
        try {
            $this->ensureRoleBelongsToCurrentSurface($role);

            return $role;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    private function currentTenantId(): ?int
    {
        if (app()->bound('saas.currentSurface') && app('saas.currentSurface') === 'merchant') {
            return $this->tenantContext->currentId();
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function ensureRoleBelongsToCurrentSurface(Role $role): void
    {
        $tenantId = $this->currentTenantId();

        if ($tenantId !== null && (int) $role->tenant_id !== $tenantId) {
            throw new Exception('Role not found for this merchant tenant.', 404);
        }

        if ($tenantId === null && $role->tenant_id !== null) {
            throw new Exception('Tenant role is not available on the owner workspace.', 404);
        }
    }
}
