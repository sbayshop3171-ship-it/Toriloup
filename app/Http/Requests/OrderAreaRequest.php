<?php

namespace App\Http\Requests;

use App\Models\OrderArea;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderAreaRequest extends FormRequest
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
        $tenantId = app(TenantContext::class)->currentId();
        $orderArea = $this->route('orderArea');
        $orderAreaId = is_object($orderArea) ? $orderArea->id : $orderArea;

        return [
            'country'       => ['required', 'string', 'max:900'],
            'state'         => ['required', 'string', 'max:900', Rule::unique("order_areas", "state")->ignore($orderAreaId)->where(function ($query) use ($tenantId) {
                return $query
                    ->where('city', request('city'))
                    ->where('tenant_id', $tenantId);
            })],
            'city'          => ['required', 'string', 'max:900', Rule::unique("order_areas", "city")->ignore($orderAreaId)->where(function ($query) use ($tenantId) {
                return $query
                    ->where('state', request('state'))
                    ->where('tenant_id', $tenantId);
            })],
            'shipping_cost' => ['required', 'string', 'max:900'],
            'status'        => ['required', 'numeric', 'max:24'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $orderArea = $this->route('orderArea');
            $orderAreaId = is_object($orderArea) ? $orderArea->id : $orderArea;
            $country = OrderArea::query()
                ->where('country', $this->country)
                ->where('state', $this->state)
                ->where('city', $this->city)
                ->when($orderAreaId, fn ($query) => $query->where('id', '!=', $orderAreaId))
                ->first();
            if ($country) {
                $validator->getMessageBag()->add('country', trans('all.message.country_exist'));
            }
        });
    }
}
