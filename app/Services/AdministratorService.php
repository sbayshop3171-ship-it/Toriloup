<?php

namespace App\Services;

use App\Enums\Ask;
use App\Enums\Role as EnumRole;
use App\Http\Requests\AdministratorRequest;
use App\Http\Requests\ChangeImageRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\UserChangePasswordRequest;
use App\Libraries\AppLibrary;
use App\Libraries\QueryExceptionLibrary;
use App\Models\PlatformRole;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdministratorService
{
    public $user;
    public $userFilter = ['name', 'email', 'username', 'status', 'phone'];

    /**a
     * @throws Exception
     */
    public function list(PaginateRequest $request)
    {
        try {
            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_type') ?? 'desc';

            $query = User::with('media', 'roles');

            if ($tenantId = $this->currentTenantId()) {
                $query->whereHas('tenantMembers', fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active'))
                    ->whereHas('roles', fn ($query) => $query->where('id', EnumRole::MANAGER));
            } else {
                $query->role(EnumRole::ADMIN);
            }

            return $query->where(function ($query) use ($requests) {
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->userFilter)) {
                        if ($key == 'phone') {
                            $query->whereRaw("CONCAT(country_code, phone) LIKE ?", ["%{$request}%"]);
                        } else {
                            $query->where($key, 'like', '%' . $request . '%');
                        }
                    }
                }
            })->orderBy($orderColumn, $orderType)->$method(
                $methodValue
            );
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function store(AdministratorRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $this->user = User::create([
                    'name'              => $request->name,
                    'email'             => $request->email,
                    'phone'             => $request->phone,
                    'username'          => AppLibrary::username($request->name),
                    'password'          => Hash::make($request->password),
                    'status'            => $request->status,
                    'email_verified_at' => now(),
                    'country_code'      => $request->country_code,
                    'is_guest'          => Ask::NO,
                ]);
                if ($tenantId = $this->currentTenantId()) {
                    $this->user->assignRole($this->ensureManagerRole());
                    TenantMember::query()->firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'user_id' => $this->user->id,
                        ],
                        [
                            'role_id' => $this->merchantStaffRole()->id,
                            'status' => 'active',
                            'invited_by_user_id' => Auth::id(),
                            'joined_at' => now(),
                        ]
                    );
                } else {
                    $this->user->assignRole(EnumRole::ADMIN);
                }
            });
            return $this->user;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(AdministratorRequest $request, User $administrator)
    {
        try {
            $this->ensureTenantAdministrator($administrator);

            DB::transaction(function () use ($administrator, $request) {
                $this->user               = $administrator;
                $this->user->name         = $request->name;
                $this->user->email        = $request->email;
                $this->user->phone        = $request->phone;
                $this->user->status       = $request->status;
                $this->user->country_code = $request->country_code;

                if ($request->password) {
                    $this->user->password = Hash::make($request->password);
                }
                $this->user->save();
            });
            return $this->user;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function destroy(User $administrator)
    {
        try {
            $this->ensureTenantAdministrator($administrator);

            if ($this->currentTenantId() !== null) {
                if (Auth::user()->id == $administrator->id) {
                    throw new Exception(trans('The permission is denied.'), 422);
                }

                DB::transaction(function () use ($administrator) {
                    TenantMember::query()
                        ->where('tenant_id', $this->currentTenantId())
                        ->where('user_id', $administrator->id)
                        ->delete();

                    if ($administrator->tenantMembers()->doesntExist()) {
                        $administrator->addresses()->delete();
                        $administrator->delete();
                    }
                });

                return;
            }

            if (Auth::user()->id != $administrator->id && $administrator->id != 1) {
                if ($administrator->hasRole(EnumRole::ADMIN)) {
                    DB::transaction(function () use ($administrator) {
                        $administrator->removeRole($administrator->roles[0]->id);
                        $administrator->addresses()->delete();
                        $administrator->delete();
                    });
                } else {
                    throw new Exception(trans('The permission is denied.'), 422);
                }
            } else {
                throw new Exception(trans('The permission is denied.'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function show(User $administrator): User
    {
        try {
            $this->ensureTenantAdministrator($administrator);

            if ($this->currentTenantId() !== null) {
                return $administrator;
            }

            if ($administrator->hasRole(EnumRole::ADMIN)) {
                return $administrator;
            } else {
                throw new Exception(trans('The permission is denied.'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function changePassword(UserChangePasswordRequest $request, User $administrator): User
    {
        try {
            $this->ensureTenantAdministrator($administrator);

            if ($this->currentTenantId() !== null) {
                $administrator->password = Hash::make($request->password);
                $administrator->save();
                return $administrator;
            }

            if ($administrator->hasRole(EnumRole::ADMIN)) {
                $administrator->password = Hash::make($request->password);
                $administrator->save();
                return $administrator;
            } else {
                throw new Exception(trans('The permission is denied.'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function changeImage(ChangeImageRequest $request, User $administrator): User
    {
        try {
            $this->ensureTenantAdministrator($administrator);

            if ($this->currentTenantId() !== null) {
                $administrator->clearMediaCollection('profile');
                $administrator->addMediaFromRequest('image')->toMediaCollection('profile');
                return $administrator;
            }

            if ($administrator->hasRole(EnumRole::ADMIN)) {
                $administrator->clearMediaCollection('profile');
                $administrator->addMediaFromRequest('image')->toMediaCollection('profile');
                return $administrator;
            } else {
                throw new Exception(trans('The permission is denied.'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    private function currentTenantId(): ?int
    {
        return app(TenantContext::class)->currentId();
    }

    /**
     * @throws Exception
     */
    private function ensureTenantAdministrator(User $administrator): void
    {
        $tenantId = $this->currentTenantId();

        if ($tenantId === null) {
            return;
        }

        $belongsToTenant = TenantMember::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $administrator->id)
            ->where('status', 'active')
            ->exists();

        if (!$belongsToTenant || $administrator->hasRole(EnumRole::ADMIN) || $administrator->hasRole(EnumRole::CUSTOMER)) {
            throw new Exception(trans('The permission is denied.'), 404);
        }
    }

    private function ensureManagerRole(): \Spatie\Permission\Models\Role
    {
        $role = \Spatie\Permission\Models\Role::query()->find(EnumRole::MANAGER);

        if ($role !== null) {
            return $role;
        }

        $role = new \Spatie\Permission\Models\Role();
        $role->id = EnumRole::MANAGER;
        $role->name = 'Manager';
        $role->guard_name = 'sanctum';
        $role->save();

        return $role;
    }

    private function merchantStaffRole(): PlatformRole
    {
        return PlatformRole::query()->firstOrCreate(
            ['code' => 'merchant_staff'],
            [
                'name' => 'Merchant Staff',
                'scope' => 'merchant',
                'is_system' => true,
            ]
        );
    }
}
