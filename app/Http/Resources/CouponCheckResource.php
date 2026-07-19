<?php

namespace App\Http\Resources;

use App\Enums\DiscountType;
use App\Libraries\AppLibrary;
use App\Models\Tenant;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Currency\VisitorCurrencyResolver;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponCheckResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $amount = $this->amount($request);

        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'discount'          => $amount,
            "flat_discount"     => AppLibrary::flatAmountFormat($amount),
            "convert_discount"  => AppLibrary::convertAmountFormat($amount),
            "currency_discount" => $this->currencyAmount($amount, $request),
        ];
    }

    public function amount($request)
    {
        $maximumDiscount = $this->displayAmount((float) $this->maximum_discount, $request);

        if ($this->discount_type == DiscountType::FIXED) {
            $amount = $this->displayAmount((float) $this->discount, $request);
            if ($amount > $maximumDiscount) {
                return $maximumDiscount;
            } else {
                return $amount;
            }
        } else {
            $amount = ($request->total * ($this->discount) / 100);
            if ($amount > $maximumDiscount) {
                return $maximumDiscount;
            } else {
                return $amount;
            }
        }
    }

    private function displayAmount(float $amount, $request): float
    {
        return app(CurrencyConversionService::class)
            ->priceForRequest($amount, $request, $this->tenantFromRequest($request))['display_amount'];
    }

    private function currencyAmount(float $amount, $request): string
    {
        $display = app(VisitorCurrencyResolver::class)->resolve($request, $this->tenantFromRequest($request));

        return app(CurrencyConversionService::class)->format(
            $amount,
            $display['code'],
            $display['symbol'],
            $display['minor_unit'],
            env('CURRENCY_POSITION')
        );
    }

    private function tenantFromRequest($request): ?Tenant
    {
        $tenant = $request?->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
