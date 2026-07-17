<?php

namespace App\Services\Saas;

use App\Enums\Role as EnumRole;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MerchantPermissionBootstrapper
{
    /**
     * These are the day-one merchant store-operation permissions.
     * Owner/platform-only permissions stay outside this list.
     *
     * @return array<string, string>
     */
    public function permissionUrls(): array
    {
        return [
            'dashboard' => 'dashboard',
            'products' => 'products',
            'products_create' => 'products/create',
            'products_edit' => 'products/edit',
            'products_delete' => 'products/delete',
            'products_show' => 'products/show',
            'purchase' => 'purchase',
            'purchase_create' => 'purchase/create',
            'purchase_edit' => 'purchase/edit',
            'purchase_delete' => 'purchase/delete',
            'purchase_show' => 'purchase/show',
            'damages' => 'damages',
            'damage_create' => 'damages/create',
            'damage_edit' => 'damages/edit',
            'damage_delete' => 'damages/delete',
            'damage_show' => 'damages/show',
            'stock' => 'stock',
            'reviews' => 'reviews',
            'pos' => 'pos',
            'pos-orders' => 'pos-orders',
            'online-orders' => 'online-orders',
            'return-orders' => 'return-orders',
            'return_order_create' => 'return-orders/create',
            'return_order_edit' => 'return-orders/edit',
            'return_order_delete' => 'return-orders/delete',
            'return_order_show' => 'return-orders/show',
            'return-and-refunds' => 'return-and-refunds',
            'coupons' => 'coupons',
            'coupons_create' => 'coupons/create',
            'coupons_edit' => 'coupons/edit',
            'coupons_delete' => 'coupons/delete',
            'coupons_show' => 'coupons/show',
            'promotions' => 'promotions',
            'promotions_create' => 'promotions/create',
            'promotions_edit' => 'promotions/edit',
            'promotions_delete' => 'promotions/delete',
            'promotions_show' => 'promotions/show',
            'product-sections' => 'product-sections',
            'product-sections_create' => 'product-sections/create',
            'product-sections_edit' => 'product-sections/edit',
            'product-sections_delete' => 'product-sections/delete',
            'product-sections_show' => 'product-sections/show',
            'push-notifications' => 'push-notifications',
            'push-notifications_create' => 'push-notifications/create',
            'push-notifications_edit' => 'push-notifications/edit',
            'push-notifications_delete' => 'push-notifications/delete',
            'push-notifications_show' => 'push-notifications/show',
            'subscribers' => 'subscribers',
            'administrators' => 'administrators',
            'administrators_create' => 'administrators/create',
            'administrators_edit' => 'administrators/edit',
            'administrators_delete' => 'administrators/delete',
            'administrators_show' => 'administrators/show',
            'customers' => 'customers',
            'customers_create' => 'customers/create',
            'customers_edit' => 'customers/edit',
            'customers_delete' => 'customers/delete',
            'customers_show' => 'customers/show',
            'employees' => 'employees',
            'employees_create' => 'employees/create',
            'employees_edit' => 'employees/edit',
            'employees_delete' => 'employees/delete',
            'employees_show' => 'employees/show',
            'transactions' => 'transactions',
            'sales-report' => 'sales-report',
            'products-report' => 'products-report',
            'credit-balance-report' => 'credit-balance-report',
            'settings' => 'settings',
        ];
    }

    public function ensureManagerRoleHasStorePermissions(): Role
    {
        $role = Role::query()->find(EnumRole::MANAGER);

        if ($role === null) {
            $role = new Role();
            $role->id = EnumRole::MANAGER;
            $role->name = 'Manager';
            $role->guard_name = 'sanctum';
            $role->save();
        }

        if ($role->guard_name !== 'sanctum') {
            $role->forceFill(['guard_name' => 'sanctum'])->save();
        }

        $permissions = collect($this->permissionUrls())
            ->map(fn (string $url, string $name) => Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'sanctum'],
                [
                    'title' => Str::headline(str_replace(['-', '_'], ' ', $name)),
                    'url' => $url,
                ]
            ))
            ->values();

        $role->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh();
    }
}
