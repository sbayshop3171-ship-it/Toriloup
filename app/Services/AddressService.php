<?php

namespace App\Services;


use App\Http\Requests\PaginateRequest;
use Exception;
use App\Models\Address;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\AddressRequest;
use App\Libraries\QueryExceptionLibrary;

class AddressService
{

    public $addressFilter = ['full_name', 'email', 'country_code', 'phone', 'country', 'city', 'state', 'zip_code', 'address'];

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

            return Address::where('user_id', auth()->user()->id)->where(function ($query) use ($requests) {
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
    public function store(AddressRequest $request)
    {
        try {
            return Address::create($request->validated() + ['user_id' => Auth::user()->id]);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(AddressRequest $request, Address $address)
    {
        try {
            $this->ensureOwnership($address);
            return tap($address)->update($request->validated());
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function show(Address $address): Address
    {
        try {
            $this->ensureOwnership($address);

            return $address;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function destroy(Address $address): void
    {
        try {
            $this->ensureOwnership($address);
            $address->delete();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    private function ensureOwnership(Address $address): void
    {
        if ((int) $address->user_id !== (int) Auth::id()) {
            throw new Exception('The requested address does not belong to the authenticated customer.', 403);
        }
    }
}
