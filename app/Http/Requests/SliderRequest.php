<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SliderRequest extends FormRequest
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
        $slider = $this->route('slider') ?? $this->route('sliderId');
        $sliderId = is_object($slider) ? $slider->id : $slider;
        $tenantId = app(TenantContext::class)->currentId($this);
        $tenantScope = fn ($rule) => $tenantId === null
            ? $rule->whereNull('tenant_id')
            : $rule->where('tenant_id', $tenantId);

        return [
            'title'        => [
                'required',
                'string',
                'max:190',
                $tenantScope(Rule::unique("sliders", "title"))
                    ->ignore($sliderId)
            ],
            'description' => ['nullable'],
            'status'      => ['required', 'numeric'],
            'image'       => $sliderId ? ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'] : ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
