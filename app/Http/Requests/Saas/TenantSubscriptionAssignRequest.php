<?php

namespace App\Http\Requests\Saas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantSubscriptionAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_code' => ['required', 'string', 'max:60'],
            'billing_interval' => ['sometimes', Rule::in(['monthly', 'yearly'])],
            'metadata_json' => ['nullable', 'array'],
        ];
    }
}
