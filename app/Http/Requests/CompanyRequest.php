<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->is('api/merchant/settings/company')) {
            $this->merge(['company_website' => null]);
        }
    }

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
            'company_name'         => ['required', 'string', 'max:190'],
            'company_email'        => ['required', 'email', 'max:190'],
            'company_calling_code' => ['required', 'string', 'max:20'],
            'company_phone'        => ['required', 'string', 'max:20'],
            'company_website'      => ['nullable', 'url', 'max:500'],
            'company_city'         => ['required', 'string', 'max:190'],
            'company_state'        => ['required', 'string', 'max:190'],
            'company_country_code' => ['required', 'string', 'max:190'],
            'company_zip_code'     => ['required', 'string', 'max:190'],
            'company_latitude'     => ['nullable', 'max:190'],
            'company_longitude'    => ['nullable', 'max:190'],
            'company_address'      => ['required', 'string', 'max:500'],
        ];
    }
}
