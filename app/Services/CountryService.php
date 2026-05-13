<?php

namespace App\Services;



use Carbon\Carbon;
use Exception;
use App\Models\Country;
use App\Libraries\AppLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Requests\CountryRequest;
use App\Http\Requests\PaginateRequest;
use App\Libraries\QueryExceptionLibrary;

class CountryService
{
    public object $country;
    private CountryMetadataService $countryMetadataService;
    private static ?bool $hasCurrencyColumns = null;

    public function __construct(CountryMetadataService $countryMetadataService)
    {
        $this->countryMetadataService = $countryMetadataService;
    }

    protected array $countryFilter = [
        'name',
        'code',
        'status',
    ];

    protected array $exceptFilter = [
        'excepts'
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
            $orderType   = $request->get('order_type') ?? 'desc';

            return Country::where(function ($query) use ($requests) {
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->countryFilter)) {
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
    public function store(CountryRequest $request)
    {
        try {
            $countryData = [
                'name'             => $request->name,
                'code'             => $request->code,
                'status'           => $request->status,
            ];

            if ($this->hasCurrencyColumns()) {
                $countryMetadata = $this->countryMetadataService->byCountryCode($request->code);
                $countryData['currency_code']   = $countryMetadata['currency_code'];
                $countryData['currency_symbol'] = $countryMetadata['currency_symbol'];
            }

            $this->country = Country::create($countryData);
            return $this->country;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(CountryRequest $request, Country $country)
    {
        try {
            DB::transaction(function () use ($request, $country) {
                $country->name             = $request->name;
                $country->code             = $request->code;
                $country->status           = $request->status;

                if ($this->hasCurrencyColumns()) {
                    $countryMetadata = $this->countryMetadataService->byCountryCode($request->code);
                    $country->currency_code   = $countryMetadata['currency_code'];
                    $country->currency_symbol = $countryMetadata['currency_symbol'];
                }

                $country->save();

                $this->country = $country;
            });
            return $this->country;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function destroy(Country $country): void
    {
        try {
            $country->delete();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function show(Country $country): Country
    {
        try {
            return $country;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    private function hasCurrencyColumns(): bool
    {
        if (self::$hasCurrencyColumns !== null) {
            return self::$hasCurrencyColumns;
        }

        return self::$hasCurrencyColumns = Schema::hasColumns('countries', ['currency_code', 'currency_symbol']);
    }
}
