<?php

namespace App\Http\Resources;


use Illuminate\Http\Resources\Json\JsonResource;

class PaymentGatewayResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */

    public function toArray($request) : array
    {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'slug'    => $this->slug,
            'status'  => $this->status,
            'options' => $this->gatewayOptions ? GatewayOptionsResource::collection(
                $this->gatewayOptions->reject(fn ($option) => $this->slug === 'paypal' && $option->option === 'paypal_app_id')
            ) : []
        ];
    }

}
