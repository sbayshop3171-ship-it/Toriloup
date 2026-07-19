<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tenants', 'billing_exempt_until_plan_change')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->boolean('billing_exempt_until_plan_change')->default(false)->after('plan_code');
            });
        }

        if (!Schema::hasColumn('tenants', 'billing_grandfathered_at')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->timestamp('billing_grandfathered_at')->nullable()->after('billing_exempt_until_plan_change');
            });
        }

        DB::table('tenants')
            ->where('billing_exempt_until_plan_change', false)
            ->update([
                'billing_exempt_until_plan_change' => true,
                'billing_grandfathered_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'billing_grandfathered_at')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('billing_grandfathered_at');
            });
        }

        if (Schema::hasColumn('tenants', 'billing_exempt_until_plan_change')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('billing_exempt_until_plan_change');
            });
        }
    }
};
