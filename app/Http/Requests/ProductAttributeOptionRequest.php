<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductAttributeOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() : array
    {
        $productAttributeOption = $this->route('productAttributeOption') ?? $this->route('optionId');
        $productAttributeOptionId = is_object($productAttributeOption) ? $productAttributeOption->id : $productAttributeOption;
        $productAttribute = $this->route('productAttribute') ?? $this->route('attributeId');
        $productAttributeId = is_object($productAttribute) ? $productAttribute->id : $productAttribute;
        $tenantId = app(TenantContext::class)->currentId($this);

        return [
            'name' => [
                'required',
                'string',
                'max:190',
                Rule::unique('product_attribute_options', 'name')
                    ->ignore($productAttributeOptionId)
                    ->where('product_attribute_id', $productAttributeId)
                    ->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))
            ]
        ];
    }
}
