<?php

namespace App\Http\Requests;

use App\Enums\OrderType;
use App\Models\PaymentGateway;
use App\Rules\ValidJsonOrder;
use App\Services\Saas\TenantPaymentMethodCatalogService;
use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
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
            'subtotal'        => ['required', 'numeric'],
            'discount'        => ['nullable', 'numeric'],
            'shipping_charge' => (int) request('order_type') == OrderType::DELIVERY ? ['required', 'numeric'] : ['nullable'],
            'tax'             => ['required', 'numeric'],
            'total'           => ['required', 'numeric'],
            'order_type'      => ['required', 'numeric'],
            'shipping_id'     => (int) request('order_type') == OrderType::DELIVERY ? ['required', 'numeric'] : ['nullable'],
            'billing_id'      => (int) request('order_type') == OrderType::DELIVERY ? ['required', 'numeric'] : ['nullable'],
            'outlet_id'       => (int) request('order_type') == OrderType::PICK_UP ? ['required', 'numeric', 'not_in:0'] : ['nullable'],
            'coupon_id'       => ['nullable', 'numeric'],
            'source'          => ['required', 'numeric'],
            'payment_method'  => ['required', 'numeric'],
            'products'        => ['required', 'json', new ValidJsonOrder]
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (request('order_type') != OrderType::DELIVERY && request('order_type') != OrderType::PICK_UP) {
                $validator->errors()->add('order_type', 'This order type is disabled now you can try another order type right now or call the management.');
            }

            $tenant = $this->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));

            if ($tenant === null) {
                return;
            }

            $paymentGateway = PaymentGateway::query()->find((int) $this->input('payment_method'));

            if ($paymentGateway === null) {
                $validator->errors()->add('payment_method', 'The selected payment method is invalid.');

                return;
            }

            $activeGatewaySlugs = app(TenantPaymentMethodCatalogService::class)->activeGatewaySlugsForTenant($tenant);

            if (!in_array($paymentGateway->slug, $activeGatewaySlugs, true)) {
                $validator->errors()->add('payment_method', 'This payment method is not enabled for this store.');
            }
        });
    }
}
