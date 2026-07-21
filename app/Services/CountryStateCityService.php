<?php

namespace App\Services;


use App\Enums\Status;
use App\Libraries\QueryExceptionLibrary;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Exception;
use Illuminate\Support\Facades\Log;



class CountryStateCityService
{

    /**
     * @throws Exception
     */
    public function countries()
    {
        try {
            return Country::where('status', Status::ACTIVE)->orderBy('name', 'asc')->get();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function statesByCountry($country_name)
    {
        $country  = Country::where('name', '=', $country_name)->where('status', Status::ACTIVE)->first();
        if (!$country) {
            return [];
        }
        try {
            return State::query()
                ->where('country_id', $country->id)
                ->where('status', Status::ACTIVE)
                ->withCount(['cities as active_cities_count' => fn ($query) => $query->where('status', Status::ACTIVE)])
                ->orderBy('name', 'asc')
                ->get();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function citiesByState($state_name)
    {
        $state = State::where('name', '=', $state_name)
            ->where('status', Status::ACTIVE)
            ->whereHas('country', function ($query) {
                $query->where('status', Status::ACTIVE);
            })
            ->first();

        if (!$state) {
            return [];
        }

        try {
            return City::where('state_id', $state->id)->where('status', Status::ACTIVE)->orderBy('name', 'asc')->get();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
