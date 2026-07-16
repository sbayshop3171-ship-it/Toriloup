<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('return_and_refunds')) {
            return;
        }

        Schema::table('return_and_refunds', function (Blueprint $table) {
            $table->string('order_serial_no', 191)->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('return_and_refunds')) {
            return;
        }

        Schema::table('return_and_refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('order_serial_no')->change();
        });
    }
};
