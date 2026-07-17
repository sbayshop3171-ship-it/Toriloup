<?php

namespace Database\Seeders;

use App\Enums\Role as EnumRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;


class RolePermissionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $adminRole = Role::find(EnumRole::ADMIN);
        $adminRole?->givePermissionTo(Permission::all());

        $branchManager = Role::find(3);
        if ($branchManager) {
            $branchManagerPermissions = [
                'dashboard',
                'products',
                'products_create',
                'products_edit',
                'products_delete',
                'products_show',
                'purchase',
                'purchase_create',
                'purchase_edit',
                'purchase_delete',
                'purchase_show',
                'damages',
                'damage_create',
                'damage_edit',
                'damage_delete',
                'damage_show',
                'stock',
                'reviews',
                'pos',
                'pos-orders',
                'online-orders',
                'return-and-refunds',
                'return-orders',
                'return_order_create',
                'return_order_edit',
                'return_order_delete',
                'return_order_show',
                'coupons',
                'coupons_create',
                'coupons_edit',
                'coupons_delete',
                'coupons_show',
                'promotions',
                'promotions_create',
                'promotions_edit',
                'promotions_delete',
                'promotions_show',
                'product-sections',
                'product-sections_create',
                'product-sections_edit',
                'product-sections_delete',
                'product-sections_show',
                'push-notifications',
                'push-notifications_create',
                'push-notifications_delete',
                'push-notifications_show',
                'subscribers',
                'administrators',
                'administrators_create',
                'administrators_edit',
                'administrators_delete',
                'administrators_show',
                'customers',
                'customers_create',
                'customers_edit',
                'customers_delete',
                'customers_show',
                'employees',
                'employees_create',
                'employees_edit',
                'employees_delete',
                'employees_show',
                'transactions',
                'sales-report',
                'products-report',
                'credit-balance-report',
                'settings',
            ];
            $branchManagerPermissions = Permission::whereIn('name', $branchManagerPermissions)->get();
            $branchManager->givePermissionTo($branchManagerPermissions);
        }

        $posOperatorManager = Role::find(4);
        if ($posOperatorManager) {
            $posOperatorManagerPermissions = [
                'dashboard',
                'pos',
                'pos-orders'
            ];
            $posOperatorManagerPermissions = Permission::whereIn('name', $posOperatorManagerPermissions)->get();
            $posOperatorManager->givePermissionTo($posOperatorManagerPermissions);
        }
    }
}
