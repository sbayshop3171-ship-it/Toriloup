<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
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
        $unit = $this->route('unit') ?? $this->route('unitId');
        $unitId = is_object($unit) ? $unit->id : $unit;
        $tenantId = app(TenantContext::class)->currentId($this);

        return [
            'name'        => [
                'required',
                'string',
                'max:190',
                Rule::unique('units', 'name')
                    ->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))
                    ->ignore($unitId)
            ],
            'code'              => [
                'required',
                'string',
                'max:20',
                Rule::unique('units', 'code')
                    ->when($tenantId !== null, fn ($rule) => $rule->where('tenant_id', $tenantId))
                    ->ignore($unitId)
            ],
            'status' => ['required', 'numeric'],
        ];
    }
}
