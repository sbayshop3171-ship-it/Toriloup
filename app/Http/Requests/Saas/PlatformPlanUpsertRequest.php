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
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'archived'])],
            'visibility' => ['sometimes', Rule::in(['public', 'hidden'])],
            'is_public' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'is_recommended' => ['sometimes', 'boolean'],
            'badge_label' => ['nullable', 'string', 'max:60'],
            'currency_code' => ['sometimes', 'string', 'max:10'],
            'monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'yearly_price' => ['sometimes', 'numeric', 'min:0'],
            'prices' => ['nullable', 'array'],
            'prices.monthly' => ['nullable', 'numeric', 'min:0'],
            'prices.semiannual' => ['nullable', 'numeric', 'min:0'],
            'prices.yearly' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'transaction_fee_type' => ['sometimes', Rule::in(['none', 'fixed', 'percent'])],
            'transaction_fee_value' => ['nullable', 'numeric', 'min:0'],
            'metadata_json' => ['nullable', 'array'],
            'limits' => ['nullable', 'array'],
            'limits.*.key' => ['required_with:limits', 'string', 'max:80'],
            'limits.*.value' => ['nullable', 'integer', 'min:0'],
            'limits.*.is_unlimited' => ['sometimes', 'boolean'],
            'features' => ['nullable', 'array'],
            'features.*.code' => ['required_with:features', 'string', 'max:120'],
            'features.*.label' => ['required_with:features', 'string', 'max:160'],
            'features.*.group' => ['nullable', 'string', 'max:120'],
            'features.*.type' => ['sometimes', Rule::in(['boolean', 'text', 'integer', 'percent'])],
            'features.*.value' => ['nullable'],
            'features.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
