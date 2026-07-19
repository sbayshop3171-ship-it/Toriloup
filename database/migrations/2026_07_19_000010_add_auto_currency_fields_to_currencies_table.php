<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('currencies')) {
            return;
        }

        Schema::table('currencies', function (Blueprint $table): void {
            if (!Schema::hasColumn('currencies', 'minor_unit')) {
                $table->unsignedTinyInteger('minor_unit')->default(2)->after('code');
            }

            if (!Schema::hasColumn('currencies', 'is_auto_managed')) {
                $table->boolean('is_auto_managed')->default(false)->after('exchange_rate');
            }

            if (!Schema::hasColumn('currencies', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('is_auto_managed');
            }

            if (!Schema::hasColumn('currencies', 'rate_source')) {
                $table->string('rate_source', 80)->nullable()->after('is_enabled');
            }

            if (!Schema::hasColumn('currencies', 'rate_synced_at')) {
                $table->timestamp('rate_synced_at')->nullable()->after('rate_source');
            }

            if (!Schema::hasColumn('currencies', 'rate_metadata_json')) {
                $table->json('rate_metadata_json')->nullable()->after('rate_synced_at');
            }
        });

        Schema::table('currencies', function (Blueprint $table): void {
            try {
                $table->index(['tenant_id', 'code'], 'currencies_tenant_code_index');
            } catch (Throwable) {
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('currencies')) {
            return;
        }

        Schema::table('currencies', function (Blueprint $table): void {
            try {
                $table->dropIndex('currencies_tenant_code_index');
            } catch (Throwable) {
            }

            foreach ([
                'rate_metadata_json',
                'rate_synced_at',
                'rate_source',
                'is_enabled',
                'is_auto_managed',
                'minor_unit',
            ] as $column) {
                if (Schema::hasColumn('currencies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
