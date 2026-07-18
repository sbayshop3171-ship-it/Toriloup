<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BenefitRequest extends FormRequest
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
        $benefit = $this->route('benefit') ?? $this->route('benefitId');
        $benefitId = is_object($benefit) ? $benefit->id : $benefit;
        $tenantId = app(TenantContext::class)->currentId($this);

        return [
            'title'        => [
                'required',
                'string',
                'max:190',
                Rule::unique("benefits", "title")
                    ->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))
                    ->ignore($benefitId)
            ],
            'description' => ['required', 'string', 'max:900'],
            'status'      => ['required', 'numeric', 'max:24'],
            'image'       => $benefitId ? ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'] : ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
