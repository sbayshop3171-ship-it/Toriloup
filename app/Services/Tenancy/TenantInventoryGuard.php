<?php

namespace App\Services\Tenancy;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Purchase;

class TenantInventoryGuard
{
    public function legacyCustomerBelongsToCurrentTenant(int|string|null $legacyUserId): bool
    {
        if (!filled($legacyUserId)) {
            return false;
        }

        return Customer::query()->where('legacy_user_id', (int) $legacyUserId)->exists();
    }

    public function purchaseBelongsToCurrentTenant(int|string|null $purchaseId): bool
    {
        if (!filled($purchaseId)) {
            return false;
        }

        return Purchase::query()->whereKey((int) $purchaseId)->exists();
    }

    public function orderBelongsToCurrentTenant(int|string|null $orderId): bool
    {
        if (!filled($orderId)) {
            return false;
        }

        return Order::query()->whereKey((int) $orderId)->exists();
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    public function invalidInventoryPayload(array $products): ?string
    {
        foreach ($products as $product) {
            $productId = (int) ($product['product_id'] ?? 0);
            $itemId = (int) ($product['item_id'] ?? 0);
            $isVariation = (bool) ($product['is_variation'] ?? false);

            if ($productId < 1 || !Product::query()->whereKey($productId)->exists()) {
                return trans('all.message.product_invalid');
            }

            if ($isVariation) {
                if ($itemId < 1 || !ProductVariation::query()->whereKey($itemId)->where('product_id', $productId)->exists()) {
                    return trans('all.message.product_invalid');
                }

                continue;
            }

            if ($itemId > 0 && $itemId !== $productId) {
                return trans('all.message.product_invalid');
            }
        }

        return null;
    }
}
