<?php

namespace App\Http\Resources;


use App\Libraries\AppLibrary;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'order_serial_no'         => $this->order_serial_no,
            'user_id'                 => $this->user_id,
            "total_amount_price"      => AppLibrary::flatAmountFormat($this->total),
            "total_currency_price"    => $this->orderCurrencyAmount($this->total),
            'base_currency_code'      => $this->base_currency_code,
            'display_currency_code'   => $this->display_currency_code,
            'display_currency_symbol' => $this->display_currency_symbol,
            'payment_status'          => $this->payment_status,
            'payment_method_name'      => $this->paymentMethod?->name,
            'pos_payment_method_name' => trans("posPaymentMethod." . $this->pos_payment_method),
            'status'                  => $this->status,
            'status_name'             => trans('orderStatus.' . $this->status),
            'order_items'             => optional($this->orderProducts)->count(),
            'order_datetime'          => AppLibrary::datetime($this->order_datetime),
            'user'                    => new UserResource($this->user),
        ];
    }

    private function orderCurrencyAmount($amount): string
    {
        return app(CurrencyConversionService::class)->format(
            (float) $amount,
            $this->display_currency_code ?: env('CURRENCY', 'USD'),
            $this->display_currency_symbol ?: env('CURRENCY_SYMBOL', '$'),
            (int) ($this->display_currency_minor_unit ?? env('CURRENCY_DECIMAL_POINT', 2)),
            env('CURRENCY_POSITION')
        );
    }
}
