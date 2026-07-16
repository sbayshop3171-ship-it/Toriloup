<?php

namespace App\Http\Requests\Saas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformPlanUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'archived'])],
            'currency_code' => ['sometimes', 'string', 'max:10'],
            'monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'yearly_price' => ['sometimes', 'numeric', 'min:0'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'transaction_fee_type' => ['sometimes', Rule::in(['none', 'fixed', 'percent'])],
            'transaction_fee_value' => ['nullable', 'numeric', 'min:0'],
            'metadata_json' => ['nullable', 'array'],
            'limits' => ['nullable', 'array'],
            'limits.*.key' => ['required_with:limits', 'string', 'max:80'],
            'limits.*.value' => ['nullable', 'integer', 'min:0'],
            'limits.*.is_unlimited' => ['sometimes', 'boolean'],
        ];
    }
}
