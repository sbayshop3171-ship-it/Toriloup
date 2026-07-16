<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductBrandRequest extends FormRequest
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
        $productBrand = $this->route('productBrand');
        $productBrandId = is_object($productBrand) ? $productBrand->id : $productBrand;
        $tenantId = app(TenantContext::class)->currentId($this);

        return [
            'name'        => [
                'required',
                'string',
                'max:190',
                Rule::unique('product_brands', 'name')
                    ->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))
                    ->ignore($productBrandId)
            ],
            'description' => ['nullable', 'string', 'max:900'],
            'status'      => ['required', 'numeric', 'max:24'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048']
        ];
    }
}
