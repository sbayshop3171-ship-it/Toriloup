<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tenantScopedTables = [
        'product_categories',
        'product_brands',
        'product_attributes',
        'product_attribute_options',
        'suppliers',
        'units',
        'products',
        'product_variations',
        'product_reviews',
        'wishlists',
        'product_taxes',
        'product_videos',
        'product_seos',
        'orders',
        'order_addresses',
        'stocks',
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

        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'legacy_user_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedBigInteger('legacy_user_id')->nullable()->after('tenant_id');
                $table->index('legacy_user_id');
            });
        }

        $tenantId = $this->ensureLegacyTenantId();

        if ($tenantId !== null) {
            foreach ($this->tenantScopedTables as $tableName) {
                if (Schema::hasTable($tableName)) {
                    DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
                }
            }
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_slug_unique');
                $table->dropUnique('products_sku_unique');
            });

            Schema::table('products', function (Blueprint $table) {
                $table->unique(['tenant_id', 'slug'], 'products_tenant_slug_unique');
                $table->unique(['tenant_id', 'sku'], 'products_tenant_sku_unique');
            });
        }

        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->dropUnique('product_categories_slug_unique');
            });

            Schema::table('product_categories', function (Blueprint $table) {
                $table->unique(['tenant_id', 'slug'], 'product_categories_tenant_slug_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_tenant_slug_unique');
                $table->dropUnique('products_tenant_sku_unique');
                $table->unique('slug');
                $table->unique('sku');
            });
        }

        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->dropUnique('product_categories_tenant_slug_unique');
                $table->unique('slug');
            });
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'legacy_user_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex(['legacy_user_id']);
                $table->dropColumn('legacy_user_id');
            });
        }

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

    private function ensureLegacyTenantId(): ?int
    {
        if (!Schema::hasTable('tenants')) {
            return null;
        }

        $existingTenantId = DB::table('tenants')->orderBy('id')->value('id');

        if ($existingTenantId !== null) {
            return (int) $existingTenantId;
        }

        $hasLegacyCommerceRows = false;

        foreach (['products', 'orders', 'product_categories', 'product_brands', 'units'] as $tableName) {
            if (Schema::hasTable($tableName) && DB::table($tableName)->exists()) {
                $hasLegacyCommerceRows = true;
                break;
            }
        }

        if (!$hasLegacyCommerceRows) {
            return null;
        }

        $now = now();

        return (int) DB::table('tenants')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => config('app.name', 'Legacy Store'),
            'slug' => 'legacy-store',
            'store_code' => 'LEGACY01',
            'status' => 'active',
            'onboarding_status' => 'live',
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
