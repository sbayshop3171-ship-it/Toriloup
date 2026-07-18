<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JetBrains\PhpStorm\ArrayShape;

class RoleRequest extends FormRequest
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
    #[ArrayShape(['name' => "array"])] 
    public function rules() : array
    {
        $tenantId = null;

        if (app()->bound('saas.currentSurface') && app('saas.currentSurface') === 'merchant') {
            $tenantId = app(TenantContext::class)->currentId();
        }

        $role = $this->route('role');
        $ignoreId = is_object($role) && method_exists($role, 'getKey') ? $role->getKey() : $role;

        return [
            'name' => [
                'required',
                'string',
                'max:190',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $tenantId !== null
                        ? $query->where('tenant_id', $tenantId)
                        : $query->whereNull('tenant_id'))
                    ->ignore($ignoreId),
            ],
        ];
    }
}
