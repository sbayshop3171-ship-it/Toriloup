<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (!Schema::hasColumn('orders', 'base_currency_code')) {
                    $table->string('base_currency_code', 10)->nullable()->after('source');
                }

                if (!Schema::hasColumn('orders', 'display_currency_code')) {
                    $table->string('display_currency_code', 10)->nullable()->after('base_currency_code');
                }

                if (!Schema::hasColumn('orders', 'display_currency_symbol')) {
                    $table->string('display_currency_symbol', 16)->nullable()->after('display_currency_code');
                }

                if (!Schema::hasColumn('orders', 'display_currency_minor_unit')) {
                    $table->unsignedTinyInteger('display_currency_minor_unit')->default(2)->after('display_currency_symbol');
                }

                if (!Schema::hasColumn('orders', 'display_exchange_rate')) {
                    $table->decimal('display_exchange_rate', 19, 8)->nullable()->after('display_currency_minor_unit');
                }

                if (!Schema::hasColumn('orders', 'display_rate_source')) {
                    $table->string('display_rate_source', 80)->nullable()->after('display_exchange_rate');
                }

                if (!Schema::hasColumn('orders', 'display_rate_synced_at')) {
                    $table->timestamp('display_rate_synced_at')->nullable()->after('display_rate_source');
                }

                if (!Schema::hasColumn('orders', 'charge_currency_code')) {
                    $table->string('charge_currency_code', 10)->nullable()->after('display_rate_synced_at');
                }

                if (!Schema::hasColumn('orders', 'fx_quote_expires_at')) {
                    $table->timestamp('fx_quote_expires_at')->nullable()->after('charge_currency_code');
                }

                if (!Schema::hasColumn('orders', 'currency_snapshot_json')) {
                    $table->json('currency_snapshot_json')->nullable()->after('fx_quote_expires_at');
                }
            });
        }

        if (Schema::hasTable('stocks')) {
            Schema::table('stocks', function (Blueprint $table): void {
                if (!Schema::hasColumn('stocks', 'base_price')) {
                    $table->decimal('base_price', 19, 6)->nullable()->after('price');
                }

                if (!Schema::hasColumn('stocks', 'base_currency_code')) {
                    $table->string('base_currency_code', 10)->nullable()->after('base_price');
                }

                if (!Schema::hasColumn('stocks', 'display_currency_code')) {
                    $table->string('display_currency_code', 10)->nullable()->after('base_currency_code');
                }

                if (!Schema::hasColumn('stocks', 'display_exchange_rate')) {
                    $table->decimal('display_exchange_rate', 19, 8)->nullable()->after('display_currency_code');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stocks')) {
            Schema::table('stocks', function (Blueprint $table): void {
                foreach (['display_exchange_rate', 'display_currency_code', 'base_currency_code', 'base_price'] as $column) {
                    if (Schema::hasColumn('stocks', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                foreach ([
                    'currency_snapshot_json',
                    'fx_quote_expires_at',
                    'charge_currency_code',
                    'display_rate_synced_at',
                    'display_rate_source',
                    'display_exchange_rate',
                    'display_currency_minor_unit',
                    'display_currency_symbol',
                    'display_currency_code',
                    'base_currency_code',
                ] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
