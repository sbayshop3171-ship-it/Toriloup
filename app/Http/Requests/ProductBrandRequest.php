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
        $productBrand = $this->route('productBrand') ?? $this->route('brandId');
        $productBrandId = is_object($productBrand) ? $productBrand->id : $productBrand;
        $tenantId = app(TenantContext::class)->currentId($this);
        $tenantScope = fn ($rule) => $tenantId === null
            ? $rule->whereNull('tenant_id')
            : $rule->where('tenant_id', $tenantId);

        return [
            'name'        => [
                'required',
                'string',
                'max:190',
                $tenantScope(Rule::unique('product_brands', 'name'))
                    ->ignore($productBrandId)
            ],
            'description' => ['nullable', 'string', 'max:900'],
            'status'      => ['required', 'numeric', 'max:24'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048']
        ];
    }
}
