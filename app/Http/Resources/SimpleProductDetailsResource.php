<?php

namespace App\Http\Resources;

use App\Enums\Activity;
use App\Enums\Ask;
use App\Libraries\AppLibrary;
use App\Models\Tenant;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Http\Resources\Json\JsonResource;

class SimpleProductDetailsResource extends JsonResource
{

    public function toArray($request): array
    {
        $price = count($this->variations) > 0 ? $this->variation_price : $this->selling_price;
        $isOffer = AppLibrary::isBetweenDate($this->offer_start_date, $this->offer_end_date);
        $activeBasePrice = $isOffer ? $price - (($price / 100) * $this->discount) : $price;
        $displayPrice = app(CurrencyConversionService::class)->priceForRequest((float) $activeBasePrice, $request, $this->tenantFromRequest($request));
        $oldDisplayPrice = app(CurrencyConversionService::class)->priceForRequest((float) $price, $request, $this->tenantFromRequest($request));
        $discountDisplay = app(CurrencyConversionService::class)->priceForRequest((float) (($price / 100) * $this->discount), $request, $this->tenantFromRequest($request));
        $shippingDisplay = app(CurrencyConversionService::class)->priceForRequest((float) $this->shipping_cost, $request, $this->tenantFromRequest($request));

        return [
            'id'                        => $this->id,
            'name'                      => $this->name,
            'slug'                      => $this->slug,
            'price'                     => $displayPrice['display_amount'],
            'currency_price'            => $displayPrice['formatted'],
            'base_price'                => $displayPrice['base_amount'],
            'base_currency_code'        => $displayPrice['base_currency_code'],
            'display_currency_code'     => $displayPrice['display_currency_code'],
            'display_currency_symbol'   => $displayPrice['display_currency_symbol'],
            'display_currency_minor_unit' => $displayPrice['display_currency_minor_unit'],
            'display_exchange_rate'     => $displayPrice['display_exchange_rate'],
            'display_rate_source'       => $displayPrice['display_rate_source'],
            'display_rate_synced_at'    => $displayPrice['display_rate_synced_at'],
            'old_price'                 => $oldDisplayPrice['display_amount'],
            'old_currency_price'        => $oldDisplayPrice['formatted'],
            'old_base_price'            => $oldDisplayPrice['base_amount'],
            'discount'                  => $isOffer ? $discountDisplay['display_amount'] : 0,
            'discount_percentage'       => AppLibrary::convertAmountFormat($this->discount),
            'flash_sale'                => $this->add_to_flash_sale == Ask::YES,
            'is_offer'                  => $isOffer,
            'rating_star'               => $this->rating_star,
            'rating_star_count'         => (int) $this->rating_star_count,
            'image'                     => $this->cover,
            'images'                    => $this->previews,
            'taxes'                     => SimpleTaxResource::collection($this->taxes),
            'reviews'                   => ProductReviewResource::collection($this->reviews),
            'videos'                    => ProductVideoResource::collection($this->videos),
            'seo'                       => new ProductSeoResource($this->seo),
            'wishlist'                  => (bool)$this->wishlist,
            'details'                   => $this->description,
            'shipping_and_return'       => $this->shipping_and_return,
            'category_slug'             => $this->category?->slug,
            'unit'                      => $this->unit?->name,
            'stock'                     => $this->show_stock_out == Activity::DISABLE ? ($this->can_purchasable == Ask::NO ? (int)$this->maximum_purchase_quantity : (int)$this->stock_items_sum_quantity) : 0,
            'sku'                       => $this->sku,
            "maximum_purchase_quantity" => $this->maximum_purchase_quantity,
            'shipping'                  => [
                'shipping_type'                => $this->shipping_type,
                'shipping_cost'                => $shippingDisplay['display_amount'],
                'base_shipping_cost'           => $shippingDisplay['base_amount'],
                'base_currency_code'           => $shippingDisplay['base_currency_code'],
                'display_currency_code'        => $shippingDisplay['display_currency_code'],
                'display_exchange_rate'        => $shippingDisplay['display_exchange_rate'],
                'is_product_quantity_multiply' => $this->is_product_quantity_multiply
            ]
        ];
    }

    private function tenantFromRequest($request): ?Tenant
    {
        $tenant = $request?->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
