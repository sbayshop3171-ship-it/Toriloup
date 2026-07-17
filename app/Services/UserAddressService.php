<?php

namespace App\Services;

use App\Http\Requests\AdministratorAddressRequest;
use Exception;
use App\Models\User;
use App\Models\Address;
use App\Models\Customer;
use App\Models\TenantMember;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\PaginateRequest;
use App\Libraries\QueryExceptionLibrary;

class UserAddressService
{
    /**
     * @throws Exception
     */
    public $address;
    public $addressFilter = ['full_name', 'email', 'phone', 'address', 'state', 'city', 'zip_code', 'latitude', 'longitude'];

    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request, User $user)
    {
        try {
            $this->ensureTenantUser($user);

            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_type') ?? 'desc';

            return Address::where('user_id', $user->id)->where(function ($query) use ($requests) {
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->addressFilter)) {
                        $query->where($key, 'like', '%' . $request . '%');
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
    public function store($request, User $user): Address
    {
        try {
            $this->ensureTenantUser($user);

            DB::transaction(function () use ($request, $user) {
                $this->address = Address::create($request->validated() + ['user_id' => $user->id]);
            });
            return $this->address;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update($request, User $user, Address $address)
    {
        try {
            $this->ensureTenantUser($user);

            if ($user->id == $address->user_id) {
                return tap($address)->update($request->validated());
            } else {
                throw new Exception(trans('all.user_match'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function destroy(User $user, Address $address): void
    {
        try {
            $this->ensureTenantUser($user);

            if ($user->id == $address->user_id) {
                $address->delete();
            } else {
                throw new Exception(trans('all.user_match'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function show(User $user, Address $address): Address
    {
        try {
            $this->ensureTenantUser($user);

            if ($user->id == $address->user_id) {
                return $address;
            } else {
                throw new Exception(trans('all.user_match'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    private function ensureTenantUser(User $user): void
    {
        $tenantId = app(TenantContext::class)->currentId();

        if ($tenantId === null) {
            return;
        }

        $isTenantMember = TenantMember::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        $isTenantCustomer = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('legacy_user_id', $user->id)
            ->exists();

        if (!$isTenantMember && !$isTenantCustomer) {
            throw new Exception(trans('all.user_match'), 404);
        }
    }
}
