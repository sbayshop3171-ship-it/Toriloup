<?php

namespace App\Http\Requests\Saas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformProviderUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_type' => ['required', Rule::in(['payment', 'sms', 'mail', 'push', 'analytics', 'domain'])],
            'name' => ['required', 'string', 'max:120'],
            'status' => ['sometimes', 'boolean'],
            'config_json' => ['nullable', 'array'],
        ];
    }
}
