<?php

namespace App\Services\Saas;

use App\Http\Requests\PaginateRequest;
use App\Models\Customer;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use App\Libraries\QueryExceptionLibrary;

class MerchantCustomerService
{
    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request)
    {
        try {
            $requests = $request->all();
            $method = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType = $request->get('order_type') ?? 'desc';

            return Customer::query()
                ->with('legacyUser')
                ->where(function ($query) use ($requests) {
                    foreach (['name', 'email', 'phone', 'status'] as $filter) {
                        if (!array_key_exists($filter, $requests) || $requests[$filter] === null) {
                            continue;
                        }

                        if ($filter === 'status') {
                            $query->where($filter, $requests[$filter]);
                            continue;
                        }

                        $query->where($filter, 'like', '%'.$requests[$filter].'%');
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
    public function show(int $customerId): Customer
    {
        try {
            return Customer::query()->with('legacyUser')->findOrFail($customerId);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
