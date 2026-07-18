<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductSectionRequest extends FormRequest
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
        $productSection = $this->route('productSection') ?? $this->route('productSectionId');
        $productSectionId = is_object($productSection) ? $productSection->id : $productSection;
        $tenantId = app(TenantContext::class)->currentId($this);

        return [
            'name'        => [
                'required',
                'string',
                'max:190',
                Rule::unique("product_sections", "name")
                    ->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))
                    ->ignore($productSectionId)
            ],
            'status'      => ['required', 'numeric', 'max:24'],
        ];
    }
}
