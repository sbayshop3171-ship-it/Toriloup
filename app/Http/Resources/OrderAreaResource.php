<?php

namespace App\Http\Resources;


use App\Models\Tenant;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderAreaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $shippingCost = $this->shipping_cost;
        $currencyFields = [];

        if ($this->isFrontendRequest($request)) {
            $display = app(CurrencyConversionService::class)->priceForRequest(
                (float) $this->shipping_cost,
                $request,
                $this->tenantFromRequest($request)
            );

            $shippingCost = $display['display_amount'];
            $currencyFields = [
                'base_shipping_cost' => $display['base_amount'],
                'base_currency_code' => $display['base_currency_code'],
                'display_currency_code' => $display['display_currency_code'],
                'display_exchange_rate' => $display['display_exchange_rate'],
            ];
        }

        return [
            'id'            => $this->id,
            'country'       => $this->country,
            'state'         => $this->state,
            'city'          => $this->city,
            'shipping_cost' => $shippingCost,
            'status'        => $this->status,
        ] + $currencyFields;
    }

    private function isFrontendRequest($request): bool
    {
        return $request?->is('api/frontend/*') || $request?->is('frontend/*');
    }

    private function tenantFromRequest($request): ?Tenant
    {
        $tenant = $request?->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
