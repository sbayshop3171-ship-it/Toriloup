<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantInventoryGuard;
use Illuminate\Foundation\Http\FormRequest;

class ReturnAndRefundRequest extends FormRequest
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
        return [
            'return_reason_id' => ['required', 'numeric'],
            'note'             => ['nullable', 'string', 'max:5000'],
            'order_id'         => ['required', 'numeric'],
            'order_serial_no'  => ['required', 'string'],
            'products'         => ['required', 'json'],
            'image[]'          => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(){
        return [
            "return_reason_id.required" => "The return reason field is required."
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $inventoryGuard = app(TenantInventoryGuard::class);

            if (!$inventoryGuard->orderBelongsToCurrentTenant($this->order_id)) {
                $validator->errors()->add('order_id', 'The selected order is invalid.');
            }
        });
    }
}
