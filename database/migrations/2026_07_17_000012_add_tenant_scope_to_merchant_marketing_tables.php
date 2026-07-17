<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tenantScopedTables = [
        'coupons',
        'promotions',
        'promotion_products',
        'product_sections',
        'product_section_products',
        'subscribers',
        'push_notifications',
        'transactions',
        'return_reasons',
        'order_coupons',
    ];

    public function up(): void
    {
        foreach ($this->tenantScopedTables as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->index('tenant_id');
            });
        }

        $legacyTenantId = $this->firstTenantId();

        if ($legacyTenantId !== null) {
            foreach ($this->tenantScopedTables as $tableName) {
                if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                    DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $legacyTenantId]);
                }
            }
        }

        $this->backfillTransactionsFromOrders();
        $this->backfillOrderCouponsFromOrders();
    }

    public function down(): void
    {
        foreach ($this->tenantScopedTables as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }

    private function firstTenantId(): ?int
    {
        if (!Schema::hasTable('tenants')) {
            return null;
        }

        $tenantId = DB::table('tenants')->orderBy('id')->value('id');

        return $tenantId === null ? null : (int) $tenantId;
    }

    private function backfillTransactionsFromOrders(): void
    {
        if (
            !Schema::hasTable('transactions') ||
            !Schema::hasColumn('transactions', 'tenant_id') ||
            !Schema::hasTable('orders') ||
            !Schema::hasColumn('orders', 'tenant_id')
        ) {
            return;
        }

        DB::table('transactions')
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->get()
            ->each(function ($transaction) {
                $tenantId = DB::table('orders')->where('id', $transaction->order_id)->value('tenant_id');

                if ($tenantId !== null) {
                    DB::table('transactions')->where('id', $transaction->id)->update(['tenant_id' => $tenantId]);
                }
            });
    }

    private function backfillOrderCouponsFromOrders(): void
    {
        if (
            !Schema::hasTable('order_coupons') ||
            !Schema::hasColumn('order_coupons', 'tenant_id') ||
            !Schema::hasTable('orders') ||
            !Schema::hasColumn('orders', 'tenant_id')
        ) {
            return;
        }

        DB::table('order_coupons')
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->get()
            ->each(function ($orderCoupon) {
                $tenantId = DB::table('orders')->where('id', $orderCoupon->order_id)->value('tenant_id');

                if ($tenantId !== null) {
                    DB::table('order_coupons')->where('id', $orderCoupon->id)->update(['tenant_id' => $tenantId]);
                }
            });
    }
};
