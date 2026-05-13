<?php

namespace App\Http\Requests;

use App\Enums\Status;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'full_name'    => ['required', 'string', 'max:190'],
            'email'        => ['nullable', 'string', 'max:190'],
            'country_code' => ['required', 'string', 'max:28'],
            'phone'        => ['required', 'string', 'max:20'],
            'country'      => ['required', 'string', 'max:100'],
            'state'        => ['nullable', 'string', 'max:100'],
            'city'         => ['nullable', 'string', 'max:100'],
            'zip_code'     => ['nullable', 'string'],
            'latitude'     => ['nullable', 'numeric'],
            'longitude'    => ['nullable', 'numeric'],
            'address'      => ['required', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateLocationHierarchy($validator);
    }

    private function validateLocationHierarchy(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $countryName = trim((string)$this->input('country'));
            $stateName   = trim((string)$this->input('state'));
            $cityName    = trim((string)$this->input('city'));

            $country = Country::where('name', $countryName)->where('status', Status::ACTIVE)->first();
            if (!$country) {
                $validator->errors()->add('country', 'The selected country is unavailable.');
                return;
            }

            $activeStatesQuery = State::where('country_id', $country->id)->where('status', Status::ACTIVE);
            $countryHasStates  = (clone $activeStatesQuery)->exists();

            if ($countryHasStates && $stateName === '') {
                $validator->errors()->add('state', 'The state field is required for the selected country.');
                return;
            }

            if ($stateName === '') {
                return;
            }

            $state = (clone $activeStatesQuery)->where('name', $stateName)->first();
            if (!$state) {
                $validator->errors()->add('state', 'The selected state is invalid for the selected country.');
                return;
            }

            $activeCitiesQuery = City::where('state_id', $state->id)->where('status', Status::ACTIVE);
            $stateHasCities    = (clone $activeCitiesQuery)->exists();

            if ($stateHasCities && $cityName === '') {
                $validator->errors()->add('city', 'The city field is required for the selected state.');
                return;
            }

            if ($cityName !== '' && !(clone $activeCitiesQuery)->where('name', $cityName)->exists()) {
                $validator->errors()->add('city', 'The selected city is invalid for the selected state.');
            }
        });
    }

    public function messages(){
        return [
            'address.required' => 'The street address field is required.'
        ];
    }
}
