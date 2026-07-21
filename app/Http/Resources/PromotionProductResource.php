<?php

namespace App\Http\Resources;

use App\Enums\Ask;
use Carbon\Carbon;
use App\Libraries\AppLibrary;
use App\Models\Tenant;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionProductResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */

    public function toArray($request): array
    {
        $price = count($this->product?->variations) > 0 ? $this->product?->variation_price : $this->product?->selling_price;
        $basePrice = app(CurrencyConversionService::class)->basePriceForRequest(
            (float) $price,
            $request,
            $this->tenantFromRequest($request)
        );
        $discountedBasePrice = app(CurrencyConversionService::class)->basePriceForRequest(
            (float) ($price - (($price / 100) * $this->product?->discount)),
            $request,
            $this->tenantFromRequest($request)
        );

        return [
            'id'                                   => $this->id,
            'promotion_id'                         => $this->promotion_id,
            'promotion_product_id'                 => $this->product_id,
            'promotion_name'                       => optional($this->promotion)->name,
            'promotion_product_name'               => optional($this->product)->name,
            "promotion_product_flat_selling_price" => AppLibrary::flatAmountFormat($this->product?->selling_price),
            'promotion_product_status'             => optional($this->product)->status,
            'currency_price'                       => $basePrice['formatted'],
            'base_currency_code'                   => $basePrice['base_currency_code'],
            'base_currency_symbol'                 => $basePrice['base_currency_symbol'],
            'flash_sale'                           => $this->product?->add_to_flash_sale == Ask::YES,
            'is_offer'                             => AppLibrary::isBetweenDate($this->product?->offer_start_date, $this->product?->offer_end_date),
            'discounted_price'                     => $discountedBasePrice['formatted']
        ];
    }

    private function tenantFromRequest($request): ?Tenant
    {
        $tenant = $request?->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        return app()->bound('currentTenant') && app('currentTenant') instanceof Tenant ? app('currentTenant') : null;
    }
}
