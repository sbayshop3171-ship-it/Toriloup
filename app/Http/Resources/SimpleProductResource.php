<?php

namespace App\Http\Resources;

use App\Enums\Ask;
use App\Libraries\AppLibrary;
use App\Models\Tenant;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Http\Resources\Json\JsonResource;

class SimpleProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $price = count($this->variations) > 0 ? $this->variation_price : $this->selling_price;
        $isOffer = AppLibrary::isBetweenDate($this->offer_start_date, $this->offer_end_date);
        $discountedBasePrice = $price - (($price / 100) * $this->discount);
        $displayPrice = app(CurrencyConversionService::class)->priceForRequest((float) $price, $request, $this->tenantFromRequest($request));
        $displayDiscountedPrice = app(CurrencyConversionService::class)->priceForRequest((float) $discountedBasePrice, $request, $this->tenantFromRequest($request));

        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'price'             => $displayPrice['display_amount'],
            'base_price'        => $displayPrice['base_amount'],
            'base_currency_code'=> $displayPrice['base_currency_code'],
            'display_currency_code' => $displayPrice['display_currency_code'],
            'display_currency_symbol' => $displayPrice['display_currency_symbol'],
            'display_currency_minor_unit' => $displayPrice['display_currency_minor_unit'],
            'display_exchange_rate' => $displayPrice['display_exchange_rate'],
            'display_rate_source' => $displayPrice['display_rate_source'],
            'display_rate_synced_at' => $displayPrice['display_rate_synced_at'],
            'currency_price'    => $displayPrice['formatted'],
            'cover'             => $this->cover,
            'flash_sale'        => $this->add_to_flash_sale == Ask::YES,
            'is_offer'          => $isOffer,
            'discount_percentage' => AppLibrary::convertAmountFormat($this->discount),
            'discounted_price'  => $displayDiscountedPrice['formatted'],
            'discounted_amount' => $displayDiscountedPrice['display_amount'],
            'rating_star'       => $this->rating_star,
            'rating_star_count' => (int) $this->rating_star_count,
            'wishlist'          => (bool)$this->wishlist,
        ];
    }

    private function tenantFromRequest($request): ?Tenant
    {
        $tenant = $request?->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
