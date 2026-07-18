<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxRequest extends FormRequest
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
        $tax = $this->route('tax') ?? $this->route('taxId');
        $taxId = is_object($tax) ? $tax->id : $tax;
        $tenantId = app(TenantContext::class)->currentId($this);
        $tenantScope = fn ($rule) => $tenantId === null
            ? $rule->whereNull('tenant_id')
            : $rule->where('tenant_id', $tenantId);

        return [
            'name'              => [
                'required',
                'string',
                'max:190'
            ],
            'code'              => [
                'required',
                'string',
                'max:20',
                $tenantScope(Rule::unique("taxes", "code"))
                    ->ignore($taxId)
            ],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status'   => ['required', 'numeric'],
        ];
    }
}
