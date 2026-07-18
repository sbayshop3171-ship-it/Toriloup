<?php

namespace App\Http\Requests;

use App\Rules\IniAmount;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
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
        $promotion = $this->route('promotion') ?? $this->route('promotionId');
        $promotionId = is_object($promotion) ? $promotion->id : $promotion;
        $tenantId = app(TenantContext::class)->currentId($this);

        return [
            'name'        => [
                'required',
                'string',
                'max:190',
                Rule::unique("promotions", "name")
                    ->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))
                    ->ignore($promotionId)
            ],
            'type'     => ['required', 'numeric', 'max:24'],
            'status'     => ['required', 'numeric', 'max:24'],
            'image'      => $promotionId ? ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'] : ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
