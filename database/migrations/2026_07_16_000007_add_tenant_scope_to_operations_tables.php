<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tenantScopedTables = [
        'purchases',
        'purchase_payments',
        'damages',
        'return_orders',
        'return_and_refunds',
        'return_and_refund_products',
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
            if (Schema::hasTable('purchases')) {
                DB::table('purchases')->whereNull('tenant_id')->update(['tenant_id' => $legacyTenantId]);
            }

            if (Schema::hasTable('damages')) {
                DB::table('damages')->whereNull('tenant_id')->update(['tenant_id' => $legacyTenantId]);
            }

            if (Schema::hasTable('return_orders')) {
                DB::table('return_orders')->whereNull('tenant_id')->update(['tenant_id' => $legacyTenantId]);
            }
        }

        if (Schema::hasTable('purchase_payments')) {
            $this->backfillPurchasePayments();
        }

        if (Schema::hasTable('return_and_refunds')) {
            $this->backfillReturnAndRefunds();

            if ($legacyTenantId !== null) {
                DB::table('return_and_refunds')->whereNull('tenant_id')->update(['tenant_id' => $legacyTenantId]);
            }
        }

        if (Schema::hasTable('return_and_refund_products')) {
            $this->backfillReturnAndRefundProducts();

            if ($legacyTenantId !== null) {
                DB::table('return_and_refund_products')->whereNull('tenant_id')->update(['tenant_id' => $legacyTenantId]);
            }
        }
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

    private function backfillPurchasePayments(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE purchase_payments pp JOIN purchases p ON p.id = pp.purchase_id SET pp.tenant_id = p.tenant_id WHERE pp.tenant_id IS NULL');
            return;
        }

        DB::table('purchase_payments')
            ->whereNull('tenant_id')
            ->orderBy('id')
            ->get(['id', 'purchase_id'])
            ->each(function ($payment): void {
                $tenantId = DB::table('purchases')->where('id', $payment->purchase_id)->value('tenant_id');

                if ($tenantId !== null) {
                    DB::table('purchase_payments')->where('id', $payment->id)->update(['tenant_id' => $tenantId]);
                }
            });
    }

    private function backfillReturnAndRefunds(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE return_and_refunds rr JOIN orders o ON o.id = rr.order_id SET rr.tenant_id = o.tenant_id WHERE rr.tenant_id IS NULL');
            return;
        }

        DB::table('return_and_refunds')
            ->whereNull('tenant_id')
            ->orderBy('id')
            ->get(['id', 'order_id'])
            ->each(function ($returnAndRefund): void {
                $tenantId = DB::table('orders')->where('id', $returnAndRefund->order_id)->value('tenant_id');

                if ($tenantId !== null) {
                    DB::table('return_and_refunds')->where('id', $returnAndRefund->id)->update(['tenant_id' => $tenantId]);
                }
            });
    }

    private function backfillReturnAndRefundProducts(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE return_and_refund_products rrp JOIN return_and_refunds rr ON rr.id = rrp.return_and_refund_id SET rrp.tenant_id = rr.tenant_id WHERE rrp.tenant_id IS NULL');
            return;
        }

        DB::table('return_and_refund_products')
            ->whereNull('tenant_id')
            ->orderBy('id')
            ->get(['id', 'return_and_refund_id'])
            ->each(function ($product): void {
                $tenantId = DB::table('return_and_refunds')->where('id', $product->return_and_refund_id)->value('tenant_id');

                if ($tenantId !== null) {
                    DB::table('return_and_refund_products')->where('id', $product->id)->update(['tenant_id' => $tenantId]);
                }
            });
    }
};
