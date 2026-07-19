<?php

namespace App\Services;


use Exception;
use App\Models\Currency;
use App\Services\Currency\CurrencyCatalogService;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\CurrencyRequest;
use App\Http\Requests\PaginateRequest;
use App\Libraries\QueryExceptionLibrary;
use Dipokhalder\Settings\Facades\Settings;

class CurrencyService
{
    public function __construct(private readonly CurrencyCatalogService $currencyCatalogService)
    {
    }

    protected $currencyFilter = [
        'name',
        'symbol',
        'code',
        'is_cryptocurrency',
        'exchange_rate'
    ];

    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request)
    {
        try {
            $requests    = $request->all();
            if (app()->bound('currentTenant')) {
                $this->currencyCatalogService->ensureTenantCurrencies(app('currentTenant'));
            } else {
                $this->currencyCatalogService->seedGlobalCurrencies();
            }
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_type') ?? 'desc';

            return Currency::where(function ($query) use ($requests) {
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->currencyFilter)) {
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
    public function store(CurrencyRequest $request)
    {
        try {
            $data = $request->validated();
            $data['code'] = strtoupper((string) $data['code']);
            $data['is_auto_managed'] = false;

            return Currency::create($data);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(CurrencyRequest $request, Currency $currency)
    {
        try {
            $data = $request->validated();
            $data['code'] = strtoupper((string) $data['code']);
            $data['is_auto_managed'] = false;

            return tap($currency)->update($data);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function destroy(Currency $currency): void
    {
        try {
            if ((bool) ($currency->is_auto_managed ?? false)) {
                throw new Exception("Auto-managed currency not deletable", 422);
            }

            if (Settings::group('site')->get("site_default_currency") != $currency->id) {
                $currency->delete();
            } else {
                throw new Exception("Default currency not deletable", 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @return array{seeded: int, updated: int, source: string, failed: bool, message: string|null}
     */
    public function sync(): array
    {
        try {
            return $this->currencyCatalogService->syncRates(app()->bound('currentTenant') ? app('currentTenant') : null);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
