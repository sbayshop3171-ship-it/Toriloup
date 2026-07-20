<?php

namespace App\Services\Currency;

use App\Enums\DiscountType;
use App\Models\Coupon;
use App\Models\OrderArea;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Saas\TenantSettingsService;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;

class MerchantBaseCurrencyConversionService
{
    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
        private readonly TenantSettingsService $tenantSettingsService,
    ) {
    }

    /**
     * Convert the merchant's editable catalog/setup money fields after the store base currency changes.
     *
     * @return array<string, mixed>
     */
    public function convertCurrentAmounts(Tenant $tenant, string $fromCode, string $toCode, ?User $actor = null): array
    {
        $fromCode = strtoupper(trim($fromCode));
        $toCode = strtoupper(trim($toCode));

        if ($fromCode === '' || $toCode === '' || $fromCode === $toCode) {
            return [
                'converted' => false,
                'from_currency_code' => $fromCode,
                'to_currency_code' => $toCode,
                'exchange_rate' => 1,
                'counts' => [],
            ];
        }

        $rate = $this->currencyConversionService
            ->exchangeRateDecimalBetween($fromCode, $toCode, $tenant)
            ->toScale(8, RoundingMode::HALF_UP);

        return [
            'converted' => true,
            'from_currency_code' => $fromCode,
            'to_currency_code' => $toCode,
            'exchange_rate' => (float) (string) $rate,
            'counts' => [
                'products' => $this->convertModelColumns(
                    Product::class,
                    $tenant,
                    ['buying_price', 'selling_price', 'variation_price', 'shipping_cost'],
                    $fromCode,
                    $toCode
                ),
                'product_variations' => $this->convertModelColumns(
                    ProductVariation::class,
                    $tenant,
                    ['price'],
                    $fromCode,
                    $toCode
                ),
                'order_areas' => $this->convertModelColumns(
                    OrderArea::class,
                    $tenant,
                    ['shipping_cost'],
                    $fromCode,
                    $toCode
                ),
                'coupons' => $this->convertCoupons($tenant, $fromCode, $toCode),
                'shipping_settings' => $this->convertShippingSettings($tenant, $fromCode, $toCode, $actor),
            ],
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $columns
     */
    private function convertModelColumns(string $modelClass, Tenant $tenant, array $columns, string $fromCode, string $toCode): int
    {
        $changed = 0;

        $modelClass::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->chunkById(100, function ($models) use ($tenant, $columns, $fromCode, $toCode, &$changed): void {
                foreach ($models as $model) {
                    $dirty = false;

                    foreach ($columns as $column) {
                        $amount = $model->{$column};

                        if (!$this->isConvertibleAmount($amount)) {
                            continue;
                        }

                        $model->{$column} = $this->currencyConversionService
                            ->convertToString($amount, $fromCode, $toCode, $tenant);
                        $dirty = true;
                    }

                    if ($dirty) {
                        $model->save();
                        $changed++;
                    }
                }
            });

        return $changed;
    }

    private function convertCoupons(Tenant $tenant, string $fromCode, string $toCode): int
    {
        $changed = 0;

        Coupon::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->chunkById(100, function ($coupons) use ($tenant, $fromCode, $toCode, &$changed): void {
                foreach ($coupons as $coupon) {
                    $columns = ['minimum_order', 'maximum_discount'];

                    if ((int) $coupon->discount_type === DiscountType::FIXED) {
                        $columns[] = 'discount';
                    }

                    $dirty = false;

                    foreach ($columns as $column) {
                        $amount = $coupon->{$column};

                        if (!$this->isConvertibleAmount($amount)) {
                            continue;
                        }

                        $coupon->{$column} = $this->currencyConversionService
                            ->convertToString($amount, $fromCode, $toCode, $tenant);
                        $dirty = true;
                    }

                    if ($dirty) {
                        $coupon->save();
                        $changed++;
                    }
                }
            });

        return $changed;
    }

    private function convertShippingSettings(Tenant $tenant, string $fromCode, string $toCode, ?User $actor): int
    {
        $settings = $this->tenantSettingsService->groupForTenant($tenant, 'shipping_setup');
        $updates = [];

        foreach (['shipping_setup_flat_rate_wise_cost', 'shipping_setup_area_wise_default_cost'] as $key) {
            if (!array_key_exists($key, $settings) || !$this->isConvertibleAmount($settings[$key])) {
                continue;
            }

            $updates[$key] = $this->currencyConversionService->convertToString($settings[$key], $fromCode, $toCode, $tenant);
        }

        if ($updates !== []) {
            $this->tenantSettingsService->syncGroupForTenant($tenant, 'shipping_setup', $updates, $actor);
        }

        return count($updates);
    }

    private function isConvertibleAmount(mixed $amount): bool
    {
        if ($amount === null || $amount === '') {
            return false;
        }

        return is_numeric(str_replace(',', '', (string) $amount));
    }
}
