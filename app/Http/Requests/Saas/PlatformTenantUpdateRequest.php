<?php

namespace App\Http\Requests\Saas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformTenantUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:190'],
            'status' => ['sometimes', 'required', Rule::in(['draft', 'active', 'suspended', 'archived'])],
            'plan_code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'onboarding_status' => ['sometimes', 'required', Rule::in(['pending', 'basic_complete', 'catalog_started', 'live'])],
            'primary_locale' => ['sometimes', 'required', 'string', 'max:10'],
            'primary_currency_code' => ['sometimes', 'required', 'string', 'max:10'],
            'timezone' => ['sometimes', 'required', 'string', 'max:60'],
            'country_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}
