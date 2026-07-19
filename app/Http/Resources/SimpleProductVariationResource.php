<?php

namespace App\Http\Resources;

use App\Enums\Activity;
use App\Enums\Ask;
use App\Libraries\AppLibrary;
use App\Models\Tenant;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Http\Resources\Json\JsonResource;

class SimpleProductVariationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $isOfferActive = AppLibrary::isBetweenDate($this->product?->offer_start_date, $this->product?->offer_end_date);

        $discountedPrice = $isOfferActive
            ? $this->price - (($this->price / 100) * $this->product->discount)
            : $this->price;
        $displayPrice = app(CurrencyConversionService::class)->priceForRequest((float) $discountedPrice, $request, $this->tenantFromRequest($request));
        $oldDisplayPrice = app(CurrencyConversionService::class)->priceForRequest((float) $this->price, $request, $this->tenantFromRequest($request));
        $discountDisplay = app(CurrencyConversionService::class)->priceForRequest((float) (($this->price / 100) * $this->product->discount), $request, $this->tenantFromRequest($request));

        return [
            'id'                            => $this->id,
            'product_attribute_id'          => (int) $this->product_attribute_id,
            'product_attribute_option_id'   => (int) $this->product_attribute_option_id,
            'product_attribute_name'        => $this->productAttribute?->name,
            'product_attribute_option_name' => $this->productAttributeOption?->name,
            'price'                         => $displayPrice['display_amount'],
            'currency_price'                => $displayPrice['formatted'],
            'base_price'                    => $displayPrice['base_amount'],
            'base_currency_code'            => $displayPrice['base_currency_code'],
            'display_currency_code'         => $displayPrice['display_currency_code'],
            'display_currency_symbol'       => $displayPrice['display_currency_symbol'],
            'display_currency_minor_unit'   => $displayPrice['display_currency_minor_unit'],
            'display_exchange_rate'         => $displayPrice['display_exchange_rate'],
            'display_rate_source'           => $displayPrice['display_rate_source'],
            'display_rate_synced_at'        => $displayPrice['display_rate_synced_at'],
            'old_price'                     => $oldDisplayPrice['display_amount'],
            'old_currency_price'            => $oldDisplayPrice['formatted'],
            'old_base_price'                => $oldDisplayPrice['base_amount'],
            'discount'                      => $isOfferActive ? $discountDisplay['display_amount'] : 0,
            'discount_percentage'           => AppLibrary::convertAmountFormat($this->product?->discount),
            'sku'                           => $this->sku,
            'stock'                         => $this->product?->show_stock_out == Activity::DISABLE ? ($this->product?->can_purchasable == Ask::NO ? (int)$this->product?->maximum_purchase_quantity : (int)$this->stock_items_sum_quantity) : 0,
            "maximum_purchase_quantity"     => $this->product?->maximum_purchase_quantity,
        ];
    }

    private function tenantFromRequest($request): ?Tenant
    {
        $tenant = $request?->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
