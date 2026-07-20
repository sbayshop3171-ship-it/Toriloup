<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactions') || Schema::hasColumn('transactions', 'currency_code')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('currency_code', 10)->nullable()->after('amount');
        });

        if (!Schema::hasTable('orders')) {
            return;
        }

        DB::table('transactions')
            ->leftJoin('orders', 'orders.id', '=', 'transactions.order_id')
            ->whereNull('transactions.currency_code')
            ->select([
                'transactions.id',
                'orders.charge_currency_code',
                'orders.display_currency_code',
                'orders.base_currency_code',
            ])
            ->orderBy('transactions.id')
            ->chunkById(200, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    $currencyCode = strtoupper((string) (
                        $transaction->charge_currency_code
                        ?: $transaction->display_currency_code
                        ?: $transaction->base_currency_code
                        ?: config('currency.base_code', 'USD')
                    ));

                    DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->update(['currency_code' => substr($currencyCode, 0, 10)]);
                }
            }, 'transactions.id', 'id');
    }

    public function down(): void
    {
        if (!Schema::hasTable('transactions') || !Schema::hasColumn('transactions', 'currency_code')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn('currency_code');
        });
    }
};
