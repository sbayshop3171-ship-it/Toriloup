<?php

namespace App\Http\Requests;

use App\Models\ProductCategory;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductCategoryRequest extends FormRequest
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
        $productCategory = $this->route('productCategory') ?? $this->route('categoryId');
        $productCategoryId = is_object($productCategory) ? $productCategory->id : $productCategory;
        $tenantId = app(TenantContext::class)->currentId($this);
        $tenantScope = fn ($rule) => $tenantId === null
            ? $rule->whereNull('tenant_id')
            : $rule->where('tenant_id', $tenantId);

        return [
            'name'        => [
                'required',
                'string',
                'max:190',
                $tenantScope(Rule::unique('product_categories', 'name'))
                    ->where('parent_id', $this->input('parent_id'))
                    ->ignore($productCategoryId)
            ],
            'parent_id'   => ['nullable', 'string', 'max:900'],
            'description' => ['nullable', 'string', 'max:900'],
            'status'      => ['required', 'numeric', 'max:24'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048']
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $productCategory = $this->route('productCategory') ?? $this->route('categoryId');
                $productCategoryId = is_object($productCategory) ? $productCategory->id : $productCategory;

                if ($productCategoryId) {
                    if ($this->input('parent_id') != 'NULL') {
                        if ((int) $this->input('parent_id') === (int) $productCategoryId) {
                            $validator->errors()->add(
                                'parent_id',
                                'The parent filed and edit field is same data.'
                            );
                        } else {
                            $status = false;
                            $productCategoryParents = ProductCategory::find($this->input('parent_id'))->ancestors()->get();
                            if ($productCategoryParents) {
                                foreach ($productCategoryParents as $productCategoryParent) {
                                    if ($productCategoryParent->id == $productCategoryId) {
                                        $status = true;
                                    }
                                }
                            }
                            if ($status) {
                                $validator->errors()->add(
                                    'parent_id',
                                    'You do not select this parent. because the paren already to add it for the children.'
                                );
                            }
                        }
                    }
                }
            }
        ];
    }
}
