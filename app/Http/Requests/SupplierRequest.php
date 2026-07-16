<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
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
        $supplier = $this->route('supplier');
        $supplierId = is_object($supplier) ? $supplier->id : $supplier;
        $tenantId = app(TenantContext::class)->currentId($this);

        return [
            'company'      => ['required', 'string', 'max:190', Rule::unique('suppliers', 'company')->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))->ignore($supplierId)],
            'name'         => ['required', 'string', 'max:190', Rule::unique('suppliers', 'name')->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))->ignore($supplierId)],
            'email'        => ['nullable', 'email', 'max:190', Rule::unique('suppliers', 'email')->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))->ignore($supplierId)],
            'phone'        => ['nullable', 'string', 'max:20', Rule::unique('suppliers', 'phone')->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))->ignore($supplierId)],
            'address'      => ['nullable', 'string', 'max:500'],
            'country'      => ['nullable', 'string', 'max:200'],
            'state'        => ['nullable', 'string', 'max:200'],
            'city'         => ['nullable', 'string', 'max:200'],
            'zip_code'     => ['nullable', 'string', 'max:200'],
            'country_code' => ['nullable', 'string', 'max:20'],
            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048']
        ];
    }
}
