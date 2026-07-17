<?php

namespace App\Services;

use Exception;
use App\Enums\Ask;
use App\Models\User;
use App\Models\Customer;
use App\Enums\Role as EnumRole;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\ChangeImageRequest;
use App\Http\Requests\UserChangePasswordRequest;
use App\Libraries\QueryExceptionLibrary;
use Illuminate\Support\Str;


class CustomerService
{
    public object $user;
    public array $phoneFilter = ['phone'];
    public array $roleFilter = ['role_id'];
    public array $userFilter = ['name', 'email', 'username', 'status', 'phone'];
    public array $blockRoles = [EnumRole::ADMIN];


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
            $orderType   = $request->get('order_type') ?? 'desc';

            $query = User::with('media', 'addresses')->role(EnumRole::CUSTOMER);

            if ($tenantId = $this->currentTenantId()) {
                $query->whereIn('id', Customer::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('legacy_user_id')
                    ->select('legacy_user_id'));
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
    public function store(CustomerRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $customerRole = $this->ensureCustomerRole();

                $this->user = User::create([
                    'name'              => $request->name,
                    'email'             => $request->email,
                    'phone'             => $request->phone,
                    'username'          => $this->username($request->email),
                    'password'          => bcrypt($request->password),
                    'email_verified_at' => now(),
                    'status'            => $request->status,
                    'country_code'      => $request->country_code,
                    'is_guest'          => Ask::NO,
                ]);
                $this->user->assignRole($customerRole);

                if ($tenantId = $this->currentTenantId()) {
                    Customer::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'legacy_user_id' => $this->user->id,
                        ],
                        [
                            'uuid' => (string) Str::uuid(),
                            'name' => $this->user->name,
                            'email' => $this->user->email,
                            'phone' => $this->user->phone,
                            'country_code' => $this->user->country_code,
                            'password' => $request->password,
                            'status' => $this->user->status,
                            'is_guest' => false,
                            'email_verified_at' => now(),
                        ]
                    );
                }
            });
            return $this->user;
        } catch (Exception $exception) {
            DB::rollBack();
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(CustomerRequest $request, User $customer)
    {
        try {
            if (!in_array(EnumRole::CUSTOMER, $this->blockRoles)) {
                $this->ensureTenantCustomer($customer);

                DB::transaction(function () use ($customer, $request) {
                    $this->user               = $customer;
                    $this->user->name         = $request->name;
                    $this->user->email        = $request->email;
                    $this->user->phone        = $request->phone;
                    $this->user->status       = $request->status;
                    $this->user->country_code = $request->country_code;
                    if ($request->password) {
                        $this->user->password = Hash::make($request->password);
                    }
                    $this->user->save();

                    if ($tenantId = $this->currentTenantId()) {
                        $customerPayload = [
                            'name' => $this->user->name,
                            'email' => $this->user->email,
                            'phone' => $this->user->phone,
                            'country_code' => $this->user->country_code,
                            'status' => $this->user->status,
                        ];

                        if ($request->password) {
                            $customerPayload['password'] = Hash::make($request->password);
                        }

                        Customer::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->where('legacy_user_id', $customer->id)
                            ->update($customerPayload);
                    }
                });
                return $this->user;
            } else {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        } catch (Exception $exception) {
            DB::rollBack();
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function show(User $customer): User
    {
        try {
            if (!in_array(EnumRole::CUSTOMER, $this->blockRoles)) {
                $this->ensureTenantCustomer($customer);

                return $customer;
            } else {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function destroy(User $customer)
    {
        try {
            if (!in_array(EnumRole::CUSTOMER, $this->blockRoles) && $customer->id != 2) {
                $this->ensureTenantCustomer($customer);

                if ($customer->hasRole(EnumRole::CUSTOMER)) {
                    DB::transaction(function () use ($customer) {
                        if ($tenantId = $this->currentTenantId()) {
                            Customer::withoutGlobalScopes()
                                ->where('tenant_id', $tenantId)
                                ->where('legacy_user_id', $customer->id)
                                ->delete();

                            if (Customer::withoutGlobalScopes()->where('legacy_user_id', $customer->id)->doesntExist()) {
                                $customer->addresses()->delete();
                                $customer->delete();
                            }
                        } else {
                            $customer->addresses()->delete();
                            $customer->delete();
                        }
                    });
                } else {
                    throw new Exception(trans('all.message.permission_denied'), 422);
                }
            } else {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    private function username($email): string
    {
        $emails = explode('@', $email);
        return $emails[0] . mt_rand();
    }

    /**
     * @throws Exception
     */
    public function changePassword(UserChangePasswordRequest $request, User $customer): User
    {
        try {
            if (!in_array(EnumRole::CUSTOMER, $this->blockRoles)) {
                $this->ensureTenantCustomer($customer);

                $customer->password = Hash::make($request->password);
                $customer->save();

                if ($tenantId = $this->currentTenantId()) {
                    Customer::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('legacy_user_id', $customer->id)
                        ->update(['password' => Hash::make($request->password)]);
                }

                return $customer;
            } else {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function changeImage(ChangeImageRequest $request, User $customer): User
    {
        try {
            if (!in_array(EnumRole::CUSTOMER, $this->blockRoles)) {
                $this->ensureTenantCustomer($customer);

                if ($request->image) {
                    $customer->clearMediaCollection('profile');
                    $customer->addMediaFromRequest('image')->toMediaCollection('profile');
                }
                return $customer;
            } else {
                throw new Exception(trans('all.message.permission_denied'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    private function ensureCustomerRole(): Role
    {
        $role = Role::query()->find(EnumRole::CUSTOMER);

        if ($role !== null) {
            return $role;
        }

        $role = new Role();
        $role->id = EnumRole::CUSTOMER;
        $role->name = 'customer';
        $role->guard_name = 'sanctum';
        $role->save();

        return $role;
    }

    private function currentTenantId(): ?int
    {
        return app(TenantContext::class)->currentId();
    }

    /**
     * @throws Exception
     */
    private function ensureTenantCustomer(User $customer): void
    {
        $tenantId = $this->currentTenantId();

        if ($tenantId === null) {
            return;
        }

        $belongsToTenant = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('legacy_user_id', $customer->id)
            ->exists();

        if (!$belongsToTenant) {
            throw new Exception(trans('all.message.permission_denied'), 404);
        }
    }
}
